<?php
/**
 * Product Routes
 * GET    /products              — List products (search, filter, paginate)
 * GET    /products/:id          — Single product detail
 * POST   /products              — Create product (seller)
 * PUT    /products/:id          — Update product (seller)
 * PUT    /products/:id/pause    — Pause/resume listing
 * PUT    /products/:id/sold     — Mark as sold
 * POST   /products/:id/boost    — Boost product
 * POST   /products/:id/restock  — Restock product
 * DELETE /products/:id          — Request deletion
 * POST   /products/:id/discount — Request discount
 * POST   /products/:id/review   — Submit review
 * GET    /products/:id/reviews  — Get product reviews
 */

require_once __DIR__ . '/../middleware/seller.php';
require_once __DIR__ . '/../config/cloudinary.php';

$productId = is_numeric($action) ? (int) $action : null;
$subAction = $param; // e.g. 'pause', 'sold', 'boost', 'reviews'

// ── MY PRODUCTS (SELLER ONLY) ──
if ($method === 'GET' && $action === 'my') {
    $auth = authenticate();
    requireSeller($pdo, $auth);

        $is_deleted_check = sqlBool(false, $pdo);
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified,
                (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
            FROM products p
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ? AND p.is_deleted = $is_deleted_check
            ORDER BY p.created_at DESC
        ");
    $stmt->execute([$auth['user_id']]);

    jsonResponse(['products' => $stmt->fetchAll()]);
}

// ── LIST PRODUCTS ──
if ($method === 'GET' && !$productId) {
    $page = max(1, (int) getQueryParam('page', 1));
    $perPage = max(1, min(48, (int) getQueryParam('per_page', 20)));
    $offset = ($page - 1) * $perPage;

    $where = ["p.status = 'approved'"];
    $params = [];

    // Exclude vacation sellers
    $vacation_check = sqlBool(false, $pdo);
    $where[] = "u.vacation_mode = $vacation_check";

    // Search
    $q = trim(getQueryParam('q', ''));
    if ($q) {
        $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    // Category filter
    $cat = getQueryParam('category', '');
    if ($cat) {
        $where[] = "p.category = ?";
        $params[] = $cat;
    }

    // Price range
    $minPrice = getQueryParam('min_price');
    if ($minPrice !== null && is_numeric($minPrice)) {
        $where[] = "p.price >= ?";
        $params[] = (float) $minPrice;
    }
    $maxPrice = getQueryParam('max_price');
    if ($maxPrice !== null && is_numeric($maxPrice)) {
        $where[] = "p.price <= ?";
        $params[] = (float) $maxPrice;
    }

    // Seller filter
    $sellerId = getQueryParam('seller_id');
    if ($sellerId) {
        $where[] = "p.user_id = ?";
        $params[] = (int) $sellerId;
    }

    $seller = trim((string) getQueryParam('seller', ''));
    if ($seller !== '') {
        $where[] = "u.username = ?";
        $params[] = $seller;
    }

    $whereStr = implode(' AND ', $where);

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p JOIN users u ON p.user_id = u.id WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Sort: premium → pro → basic, boosted, then newest
    $sort = getQueryParam('sort', 'default');
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $now = $driver === 'pgsql' ? 'CURRENT_TIMESTAMP' : 'NOW()';
    
    $orderBy = match ($sort) {
        'price_asc' => "p.price ASC",
        'price_desc' => "p.price DESC",
        'popular' => "p.views DESC",
        'oldest' => "p.created_at ASC",
        default => "
            CASE u.seller_tier WHEN 'premium' THEN 1 WHEN 'pro' THEN 2 ELSE 3 END ASC,
            CASE WHEN p.boosted_until > $now THEN 1 ELSE 2 END ASC,
            p.created_at DESC
        ",
    };

    $stmt = $pdo->prepare("
        SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified, u.profile_pic as seller_pic,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
            (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.product_id = p.id) as avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) as review_count
        FROM products p
        JOIN users u ON p.user_id = u.id
        WHERE $whereStr
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // If search returned 0 results, try SOUNDEX fallback (MySQL only for now)
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if (empty($products) && $q && strlen($q) >= 3 && $driver === 'mysql') {
            $vacation_check = sqlBool(false, $pdo);
            $stmt = $pdo->prepare("
                SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified, u.profile_pic as seller_pic,
                    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
                    (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
                FROM products p
                JOIN users u ON p.user_id = u.id
                WHERE p.status = 'approved' AND u.vacation_mode = $vacation_check AND SOUNDEX(p.title) = SOUNDEX(?)
                ORDER BY $orderBy
                LIMIT $perPage
            ");
        $stmt->execute([$q]);
        $products = $stmt->fetchAll();
        $total = count($products);
    } elseif (empty($products) && $q && strlen($q) > 3 && $driver === 'pgsql') {
        $vacation_check = sqlBool(false, $pdo);
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified, u.profile_pic as seller_pic,
                (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
                (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
            FROM products p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'approved' AND u.vacation_mode = $vacation_check AND p.title ILIKE ?
            ORDER BY $orderBy
            LIMIT $perPage
        ");
        $stmt->execute(['%' . substr($q, 0, 4) . '%']);
        $products = $stmt->fetchAll();
        $total = count($products);
    }

    // Add badge data to each product
    foreach ($products as &$p) {
        $p['seller_badge'] = getBadgeData($p['seller_tier'] ?: 'basic');
        $p['is_boosted'] = $p['boosted_until'] && strtotime($p['boosted_until']) > time();
        $originalPrice = (float) ($p['original_price_before_discount'] ?? $p['original_price'] ?? 0);
        $p['discount_percent'] = $originalPrice > 0 && $originalPrice > (float) $p['price']
            ? round((1 - ((float) $p['price'] / $originalPrice)) * 100)
            : 0;
    }

    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'approved' ORDER BY category");
    $availableCategories = $categoryStmt ? $categoryStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    jsonResponse([
        'products' => $products,
        'categories' => $availableCategories,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ]
    ]);
}

// ── SINGLE PRODUCT ──
elseif ($method === 'GET' && $productId && !$subAction) {
    $stmt = $pdo->prepare("SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified,
        u.profile_pic as seller_pic, u.phone as seller_phone, u.whatsapp as seller_whatsapp,
        u.department as seller_dept, u.last_seen as seller_last_seen, u.vacation_mode as seller_vacation
        FROM products p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) jsonError('Product not found', 404);

    // Get images
    $imgStmt = $pdo->prepare("SELECT id, image_path AS image_url, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $imgStmt->execute([$productId]);
    $product['images'] = $imgStmt->fetchAll();

    // Get reviews
    $revStmt = $pdo->prepare("SELECT r.*, u.username, u.profile_pic FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 20");
    $revStmt->execute([$productId]);
    $product['reviews'] = $revStmt->fetchAll();
    $product['avg_rating'] = getAvgRating($pdo, $productId);
    $product['review_count'] = count($product['reviews']);

    // Seller stats
    $product['seller'] = getUserPublic($pdo, $product['user_id']);
    $product['seller_badge'] = getBadgeData($product['seller_tier'] ?: 'basic');
    $product['is_boosted'] = $product['boosted_until'] && strtotime($product['boosted_until']) > time();
    $product['discount_percent'] = ($product['original_price'] ?? 0) > 0 && $product['original_price'] > $product['price']
        ? round((1 - $product['price'] / $product['original_price']) * 100)
        : 0;

    // Increment views (not for product owner)
    $auth = optionalAuth();
    if (!$auth || $auth['user_id'] !== $product['user_id']) {
        $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$productId]);
    }

    jsonResponse(['product' => $product]);
}

// ── GET REVIEWS ──
elseif ($method === 'GET' && $productId && $subAction === 'reviews') {
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_pic FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$productId]);
    jsonResponse(['reviews' => $stmt->fetchAll()]);
}

// ── CREATE PRODUCT ──
elseif ($method === 'POST' && !$productId) {
    $auth = authenticate();
    requireSeller($pdo, $auth);

    if (!canAddProduct($pdo, $auth['user_id'])) {
        jsonError('You have reached your tier product limit. Upgrade to add more products.');
    }

    $body = !empty($_POST) ? $_POST : getJsonBody();

    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $price = (float) ($body['price'] ?? 0);
    $category = $body['category'] ?? 'General';
    $quantity = max(1, (int) ($body['quantity'] ?? 1));
    $promoTag = $body['promo_tag'] ?? '';
    $deliveryMethod = $body['delivery_method'] ?? 'Pickup';
    $paymentAgreement = $body['payment_agreement'] ?? 'Pay on delivery';

    if (!$title || !$description) jsonError('Title and description are required');
    if ($price <= 0) jsonError('Price must be greater than 0');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (user_id, title, description, price, category, quantity, promo_tag, 
                                delivery_method, payment_agreement, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$auth['user_id'], $title, $description, $price, $category, $quantity,
            $promoTag, $deliveryMethod, $paymentAgreement]);

        // Fix for PostgreSQL sequences
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nextId = (int)($driver === 'pgsql' ? $pdo->lastInsertId('products_id_seq') : $pdo->lastInsertId());

        if (!$nextId) {
             throw new Exception("Critical Error: Failed to retrieve ID for the newly created product. Database may be misconfigured.");
        }

        // Handle image uploads
        $maxImages = getMaxImages($pdo, $auth['user_id']);
        $imgCount = 0;
        if (isset($_FILES['images'])) {
            $files = $_FILES['images'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $fileCount && $imgCount < $maxImages; $i++) {
                $file = [
                    'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                    'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                    'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                    'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                    'size' => is_array($files['size']) ? $files['size'][$i] : $files['size'],
                ];

                if ($file['error'] !== UPLOAD_ERR_OK) continue;
                $validErr = validateImageFile($file);
                if ($validErr) continue;

                $url = uploadToCloudinary($file, 'marketplace/products');
                if ($url) {
                    $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)")
                        ->execute([$nextId, $url, $imgCount]);
                    $imgCount++;
                }
            }
        }

        $pdo->prepare("UPDATE users SET last_upload_at = NOW() WHERE id = ?")->execute([$auth['user_id']]);
        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Product submitted for review',
            'product_id' => $nextId,
        ], 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonError('Database Error: ' . $e->getMessage() . (isset($e->errorInfo) ? ' [' . implode(',', $e->errorInfo) . ']' : ''), 500);
    }
}

// ── SUBMIT REVIEW ──
elseif ($method === 'POST' && $productId && $subAction === 'review') {
    $auth = authenticate();
    $body = getJsonBody();

    $rating = (int) ($body['rating'] ?? 0);
    $comment = trim($body['comment'] ?? '');

    if ($rating < 1 || $rating > 5) jsonError('Rating must be 1-5');

    // Check product exists
    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) jsonError('Product not found', 404);
    if ($product['user_id'] === $auth['user_id']) jsonError('Cannot review your own product');

    try {
        $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)")
            ->execute([$productId, $auth['user_id'], $rating, $comment]);
        jsonSuccess('Review submitted');
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            jsonError('You already reviewed this product');
        }
        throw $e;
    }
}

// ── PAUSE/RESUME ──
elseif ($method === 'PUT' && $productId && $subAction === 'pause') {
    $auth = authenticate();

    $stmt = $pdo->prepare("SELECT status, user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $newStatus = $product['status'] === 'paused' ? 'approved' : 'paused';
    $pdo->prepare("UPDATE products SET status = ? WHERE id = ?")->execute([$newStatus, $productId]);

    jsonSuccess("Product " . ($newStatus === 'paused' ? 'paused' : 'resumed'));
}

// ── MARK AS SOLD ──
elseif ($method === 'PUT' && $productId && $subAction === 'sold') {
    $auth = authenticate();

    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$productId]);
    jsonSuccess('Product marked as sold');
}

// ── BOOST ──
elseif ($method === 'POST' && $productId && $subAction === 'boost') {
    $auth = authenticate();

    $user = getUser($pdo, $auth['user_id']);
    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $boostPrice = (float) getSetting($pdo, 'ad_boost_price', '5');
    $isFree = $user['seller_tier'] === 'premium';

    if (!$isFree && $user['balance'] < $boostPrice) {
        jsonError("Insufficient balance. Boost costs ₵$boostPrice");
    }

    if (!$isFree) {
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$boostPrice, $auth['user_id']]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'boost', ?, 'completed', ?, 'Product boost')")
            ->execute([$auth['user_id'], $boostPrice, generateRef('BST')]);
    }

    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $intervalSql = $driver === 'pgsql' ? "CURRENT_TIMESTAMP + INTERVAL '24 hours'" : "DATE_ADD(NOW(), INTERVAL 24 HOUR)";
    $pdo->prepare("UPDATE products SET boosted_until = $intervalSql WHERE id = ?")->execute([$productId]);
    jsonSuccess('Product boosted for 24 hours');
}

// ── RESTOCK ──
elseif ($method === 'POST' && $productId && $subAction === 'restock') {
    $auth = authenticate();
    $body = getJsonBody();
    $qty = max(1, (int) ($body['quantity'] ?? 1));

    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $pdo->prepare("UPDATE products SET quantity = quantity + ?, status = 'approved' WHERE id = ?")->execute([$qty, $productId]);
    jsonSuccess("Restocked with $qty items");
}

// ── REQUEST DELETION ──
elseif ($method === 'DELETE' && $productId) {
    $auth = authenticate();

    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $pdo->prepare("UPDATE products SET status = 'deletion_requested' WHERE id = ?")->execute([$productId]);
    jsonSuccess('Deletion request submitted for admin approval');
}

// ── REQUEST DISCOUNT ──
elseif ($method === 'POST' && $productId && $subAction === 'discount') {
    $auth = authenticate();
    $body = getJsonBody();
    $percent = (int) ($body['discount_percent'] ?? 0);

    if ($percent < 1 || $percent > 90) jsonError('Discount must be between 1-90%');

    $stmt = $pdo->prepare("SELECT price, user_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || $product['user_id'] !== $auth['user_id']) jsonError('Product not found', 404);

    $discountedPrice = round($product['price'] * (1 - $percent / 100), 2);

    $pdo->prepare("INSERT INTO discount_requests (product_id, seller_id, original_price, discount_percent, discounted_price) VALUES (?, ?, ?, ?, ?)")
        ->execute([$productId, $auth['user_id'], $product['price'], $percent, $discountedPrice]);

    jsonSuccess('Discount request submitted for admin approval');
}

else {
    jsonError('Product endpoint not found', 404);
}
