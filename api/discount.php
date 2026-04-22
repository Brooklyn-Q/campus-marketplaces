]
<?php
/**
 * ========================================
 * DISCOUNT API — Database Connector
 * Bridges the triptych JS dashboard to MySQL
 * ========================================
 * 
 * Endpoints (POST JSON):
 *   action: submit_discount  — Seller submits a discount for approval
 *   action: get_pending      — Admin fetches pending discounts
 *   action: approve          — Admin approves a discount
 *   action: reject           — Admin rejects a discount
 *   action: get_products     — Get all products for seller
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../backend/config/cors.php');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';

// ── AUTHENTICATION & AUTHORIZATION ──
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

// CSRF check for write actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'get_products' && $action !== 'get_pending') {
    check_csrf();
}

try {
    switch ($action) {

        // ── Get all products for seller panel (Paginated) ──
        case 'get_products':
            $limit = (int)($input['limit'] ?? 50);
            $offset = (int)($input['offset'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT id, title AS name, price, 
                COALESCE(discount_percent, 0) AS discount, 
                status 
                FROM products ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
            break;

        // ── Seller submits a discount request ──
        case 'submit_discount':
            $product_id = (int) ($input['product_id'] ?? 0);
            $discount = (int) ($input['discount'] ?? 0);

            if ($product_id <= 0 || $discount <= 0 || $discount > 90) {
                echo json_encode(['success' => false, 'error' => 'Invalid product or discount value']);
                break;
            }

            // Check product exists AND user owns it
            $check = $pdo->prepare("SELECT id, title, price, user_id FROM products WHERE id = ? AND user_id = ?");
            $check->execute([$product_id, $_SESSION['user_id']]);
            $product = $check->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'Product not found']);
                break;
            }

            // Save discount request
            $discounted_price = $product['price'] * (1 - $discount / 100);

            // Create discount_requests table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                seller_id INT NOT NULL,
                original_price DECIMAL(10,2) NOT NULL,
                discount_percent INT NOT NULL,
                discounted_price DECIMAL(10,2) NOT NULL,
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");

            $stmt = $pdo->prepare("INSERT INTO discount_requests 
                (product_id, seller_id, original_price, discount_percent, discounted_price) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $product_id,
                $product['user_id'],
                $product['price'],
                $discount,
                $discounted_price
            ]);

            echo json_encode([
                'success' => true,
                'message' => "Discount request submitted for {$product['title']}",
                'request_id' => $pdo->lastInsertId()
            ]);
            break;

        // ── Admin: get pending discount approvals ──
        case 'get_pending':
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                break;
            }
            // Create table if not exists (safe idempotent)
            $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                seller_id INT NOT NULL,
                original_price DECIMAL(10,2) NOT NULL,
                discount_percent INT NOT NULL,
                discounted_price DECIMAL(10,2) NOT NULL,
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");

            $stmt = $pdo->query("SELECT dr.*, p.title AS product_name, u.username AS seller_name
                FROM discount_requests dr
                JOIN products p ON dr.product_id = p.id
                JOIN users u ON dr.seller_id = u.id
                WHERE dr.status = 'pending'
                ORDER BY dr.created_at DESC");
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'pending' => $pending]);
            break;

        // ── Admin approves a discount ──
        case 'approve':
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                break;
            }
            $request_id = (int) ($input['request_id'] ?? 0);
            if ($request_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
                break;
            }

            $pdo->beginTransaction();

            // Get the request
            $stmt = $pdo->prepare("SELECT * FROM discount_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Request not found or already processed']);
                break;
            }

            // Update request status
            $pdo->prepare("UPDATE discount_requests SET status = 'approved' WHERE id = ?")->execute([$request_id]);

            // Update product price (apply discount)
            $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$req['discounted_price'], $req['product_id']]);

            // Log in audit
            if (function_exists('auditLog') && isset($_SESSION['user_id'])) {
                auditLog($pdo, $_SESSION['user_id'], "Approved discount #{$request_id}", 'discount', $request_id);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Discount approved']);
            break;

        // ── Admin rejects a discount ──
        case 'reject':
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                break;
            }
            $request_id = (int) ($input['request_id'] ?? 0);
            if ($request_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
                break;
            }

            $pdo->prepare("UPDATE discount_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'")->execute([$request_id]);

            if (function_exists('auditLog') && isset($_SESSION['user_id'])) {
                auditLog($pdo, $_SESSION['user_id'], "Rejected discount #{$request_id}", 'discount', $request_id);
            }

            echo json_encode(['success' => true, 'message' => 'Discount rejected']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('discount.php DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
}
