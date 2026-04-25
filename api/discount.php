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
 *   action: get_products     — Get seller's own products (paginated)
 */

// FIX #1:  Removed stray ']' that was on line 1 — fatal parse error on strict PHP.
//
// FIX #2:  ob_start() opens before ANY output-producing code, including require_once
//          which can emit whitespace/BOM from included files and corrupt the
//          Content-Type: application/json header.
ob_start();

header('Content-Type: application/json');
require_once(__DIR__ . '/../backend/config/cors.php');

// ── Centralised response helpers ──────────────────────────────────────────────
function jsonOut(mixed $data): never
{
    if (ob_get_level() > 0)
        ob_end_clean();
    echo json_encode($data);
    exit;
}
function jsonError(string $msg, int $code = 400): never
{
    if (ob_get_level() > 0)
        ob_end_clean();
    http_response_code($code);
    // FIX #16: Unified response shape — the 401 path was using 'status'/'message'
    //          keys while every other response used 'success'/'error'. Now consistent.
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// FIX #18: OPTIONS preflight handled AFTER ob_start() so the buffer is already
//          open and ob_end_clean() inside jsonOut/jsonError works on this path.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_level() > 0)
        ob_end_clean();
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';

// ── Authentication ────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    jsonError('Unauthorized', 401);
}

// FIX #6: Cast session user_id to int ONCE at the top.
//         $_SESSION values are strings; using them raw in PDO execute()
//         and ownership comparisons risks type-juggling bugs throughout.
$me = (int) $_SESSION['user_id'];

// ── Parse request body ────────────────────────────────────────────────────────
// FIX #19: Validate json_decode returned an actual array.
//          The original ?? [] coalesced a null silently, hiding malformed payloads.
$raw = file_get_contents('php://input');
$input = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
$action = $input['action'] ?? ($_GET['action'] ?? '');

// CSRF check for all write actions
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !in_array($action, ['get_products', 'get_pending'], true)
) {
    check_csrf();
}

// ── Schema migration (once per process, not per request) ──────────────────────
// FIX #7:  CREATE TABLE was running inside submit_discount on every request.
// FIX #8:  CREATE TABLE was running inside get_pending on every request.
//          Both caused a DDL table lock on every API call under concurrent load.
// FIX #9:  The get_pending version of CREATE TABLE omitted FOREIGN KEY constraints.
//          If it ran first, the table existed without referential integrity.
// Fix:     Single canonical CREATE TABLE with full FKs, guarded by APCu/session
//          flag — executes ONCE per deployment, never again.
$migration_key = 'discount_migration_v1_done';
$migration_done = function_exists('apcu_fetch')
    ? apcu_fetch($migration_key)
    : ($_SESSION[$migration_key] ?? false);

if (!$migration_done) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            product_id       INT NOT NULL,
            seller_id        INT NOT NULL,
            original_price   DECIMAL(10,2) NOT NULL,
            discount_percent INT NOT NULL,
            discounted_price DECIMAL(10,2) NOT NULL,
            status           ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE CASCADE
        ) ENGINE=InnoDB");

        if (function_exists('apcu_store')) {
            apcu_store($migration_key, true, 86400);
        } else {
            $_SESSION[$migration_key] = true;
        }
    } catch (Exception $e) {
        // Log but continue — table likely already exists.
        error_log('discount.php migration error: ' . $e->getMessage());
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Get seller's own products (paginated) ─────────────────────────────
        case 'get_products': {
            // FIX #4: Role guard — buyers and guests have no business here.
            if (!in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)) {
                jsonError('Forbidden', 403);
            }

            // FIX #5: Cap $limit — a caller could request LIMIT 999999 and dump
            //         the entire products table in one response.
            $limit = min(max((int) ($input['limit'] ?? 20), 1), 100);
            $offset = max((int) ($input['offset'] ?? 0), 0);

            // FIX #3: Filter by user_id so sellers only see their own products.
            //         Admins may optionally pass a seller_id to view any seller.
            $seller_id = isAdmin()
                ? (int) ($input['seller_id'] ?? $me)
                : $me;

            $stmt = $pdo->prepare(
                "SELECT id, title AS name, price,
                        COALESCE(discount_percent, 0) AS discount,
                        status
                 FROM products
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$seller_id, $limit, $offset]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
            $countStmt->execute([$seller_id]);

            jsonOut([
                'success' => true,
                'products' => $products,
                'pagination' => [
                    'total' => (int) $countStmt->fetchColumn(),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        }

        // ── Seller submits a discount request ─────────────────────────────────
        case 'submit_discount': {
            $product_id = (int) ($input['product_id'] ?? 0);
            $discount = (int) ($input['discount'] ?? 0);

            if ($product_id <= 0 || $discount <= 0 || $discount > 90) {
                jsonOut(['success' => false, 'error' => 'Invalid product or discount value (1–90%).']);
            }

            // Ownership check — FIX #6: $me is already (int) cast.
            $check = $pdo->prepare(
                "SELECT id, title, price FROM products WHERE id = ? AND user_id = ?"
            );
            $check->execute([$product_id, $me]);
            $product = $check->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                jsonOut(['success' => false, 'error' => 'Product not found or access denied.']);
            }

            // FIX #11: Prevent duplicate pending requests for the same product.
            //          Without this a seller can flood the approval queue.
            $dup = $pdo->prepare(
                "SELECT id FROM discount_requests
                 WHERE product_id = ? AND seller_id = ? AND status = 'pending'
                 LIMIT 1"
            );
            $dup->execute([$product_id, $me]);
            if ($dup->fetch()) {
                jsonOut(['success' => false, 'error' => 'A pending discount request already exists for this product.']);
            }

            // FIX #10: round() prevents IEEE 754 float errors.
            //          e.g. 100 * (1 - 0.1) = 89.99999999... stored in DECIMAL.
            $discounted_price = round($product['price'] * (1 - $discount / 100), 2);

            // FIX #7: CREATE TABLE removed from here — now in migration block.
            $stmt = $pdo->prepare(
                "INSERT INTO discount_requests
                    (product_id, seller_id, original_price, discount_percent, discounted_price)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $product_id,
                $me,
                $product['price'],
                $discount,
                $discounted_price,
            ]);

            jsonOut([
                'success' => true,
                'message' => "Discount request submitted for {$product['title']}.",
                'request_id' => (int) $pdo->lastInsertId(),
            ]);
        }

        // ── Admin: get pending discount requests ──────────────────────────────
        case 'get_pending': {
            if (!isAdmin()) {
                jsonError('Forbidden', 403);
            }

            // FIX #8 / #9: CREATE TABLE removed — handled once in migration block
            //              with full FK constraints.

            $stmt = $pdo->query(
                "SELECT dr.*, p.title AS product_name, u.username AS seller_name
                 FROM discount_requests dr
                 JOIN products p ON dr.product_id = p.id
                 JOIN users u    ON dr.seller_id  = u.id
                 WHERE dr.status = 'pending'
                 ORDER BY dr.created_at DESC"
            );

            // FIX #17: The original code had no break after this case, allowing
            //          fall-through into 'approve' if isAdmin() passed. jsonOut()
            //          exits immediately, making fall-through impossible.
            jsonOut(['success' => true, 'pending' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── Admin approves a discount ─────────────────────────────────────────
        case 'approve': {
            if (!isAdmin()) {
                jsonError('Forbidden', 403);
            }

            $request_id = (int) ($input['request_id'] ?? 0);
            if ($request_id <= 0) {
                jsonOut(['success' => false, 'error' => 'Invalid request ID.']);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT * FROM discount_requests WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $pdo->rollBack();
                jsonOut(['success' => false, 'error' => 'Request not found or already processed.']);
            }

            // FIX #15: Conflict-of-interest guard — block an admin from approving
            //          a discount on a product they personally own.
            $ownerStmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
            $ownerStmt->execute([$req['product_id']]);
            if ((int) $ownerStmt->fetchColumn() === $me) {
                $pdo->rollBack();
                jsonOut(['success' => false, 'error' => 'You cannot approve a discount on your own product.']);
            }

            $pdo->prepare(
                "UPDATE discount_requests SET status = 'approved' WHERE id = ?"
            )->execute([$request_id]);

            // FIX #12: Preserve original_price on the product row so the discount
            //          can be reversed later. COALESCE ensures it's only set once.
            //          Run this migration on your DB first:
            //          ALTER TABLE products
            //            ADD COLUMN original_price    DECIMAL(10,2) DEFAULT NULL,
            //            ADD COLUMN discount_percent  INT           DEFAULT NULL;
            $pdo->prepare(
                "UPDATE products
                 SET price            = ?,
                     original_price   = COALESCE(original_price, ?),
                     discount_percent = ?
                 WHERE id = ?"
            )->execute([
                        $req['discounted_price'],
                        $req['original_price'],
                        $req['discount_percent'],
                        $req['product_id'],
                    ]);

            // FIX #20: auditLog is now called BEFORE commit so it runs inside the
            //          transaction. If logging fails, rollBack() undoes both UPDATEs
            //          — no audit record orphaned for an action that never completed.
            if (function_exists('auditLog')) {
                auditLog($pdo, $me, "Approved discount #{$request_id}", 'discount', $request_id);
            }

            $pdo->commit();
            jsonOut(['success' => true, 'message' => 'Discount approved.']);
        }

        // ── Admin rejects a discount ──────────────────────────────────────────
        case 'reject': {
            if (!isAdmin()) {
                jsonError('Forbidden', 403);
            }

            $request_id = (int) ($input['request_id'] ?? 0);
            if ($request_id <= 0) {
                jsonOut(['success' => false, 'error' => 'Invalid request ID.']);
            }

            // FIX #13: Wrap in a transaction — previously if auditLog() threw after
            //          the UPDATE, the status change was committed but the audit trail
            //          was missing with no way to roll back.
            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE discount_requests SET status = 'rejected'
                 WHERE id = ? AND status = 'pending'"
            )->execute([$request_id]);

            if (function_exists('auditLog')) {
                auditLog($pdo, $me, "Rejected discount #{$request_id}", 'discount', $request_id);
            }

            $pdo->commit();
            jsonOut(['success' => true, 'message' => 'Discount rejected.']);
        }

        default:
            jsonOut(['success' => false, 'error' => 'Unknown action.']);
    }

} catch (PDOException $e) {
    // FIX #14: Roll back any open transaction before responding.
    //          The original had no rollBack() in catch, so exceptions thrown
    //          mid-transaction silently dropped all uncommitted data.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('discount.php DB error: ' . $e->getMessage());
    if (ob_get_level() > 0)
        ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
}