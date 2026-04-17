<?php
/**
 * Recommendations Route
 * GET /recommendations?context=product&id=5 — AI recommendations
 * GET /recommendations?context=home — Homepage recommendations
 */

if ($method !== 'GET') jsonError('Method not allowed', 405);

$context = getQueryParam('context', 'home');
$contextId = (int) getQueryParam('id', 0);
$limit = min(8, max(1, (int) getQueryParam('limit', 4)));

$exclude = [];
$categoryHint = '';

if ($context === 'product' && $contextId) {
    $stmt = $pdo->prepare("SELECT category, title, description FROM products WHERE id = ?");
    $stmt->execute([$contextId]);
    $product = $stmt->fetch();
    if ($product) {
        $categoryHint = $product['category'];
        $exclude[] = $contextId;
    }
}

// Try category-based recommendations first
$excludeStr = empty($exclude) ? '0' : implode(',', $exclude);

if ($categoryHint) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
        FROM products p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'approved' AND p.category = ? AND p.id NOT IN ($excludeStr) AND u.vacation_mode = 0
        ORDER BY (u.seller_tier = 'premium') DESC, p.views DESC
        LIMIT ?
    ");
    $stmt->execute([$categoryHint, $limit]);
    $products = $stmt->fetchAll();

    if (count($products) >= $limit / 2) {
        foreach ($products as &$p) $p['seller_badge'] = getBadgeData($p['seller_tier'] ?: 'basic');
        jsonResponse(['recommendations' => $products, 'source' => 'category']);
    }
}

// Fallback: trending/popular products
$driver = getenv('DB_DRIVER') ?: 'mysql';
$orderByRand = $driver === 'pgsql' ? 'RANDOM()' : 'RAND()';

$stmt = $pdo->prepare("
    SELECT p.*, u.username as seller_name, u.seller_tier, u.verified as seller_verified,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image
    FROM products p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'approved' AND p.id NOT IN ($excludeStr) AND u.vacation_mode = 0
    ORDER BY (u.seller_tier = 'premium') DESC, p.views DESC, $orderByRand
    LIMIT ?
");
$stmt->execute([$limit]);
$products = $stmt->fetchAll();

foreach ($products as &$p) $p['seller_badge'] = getBadgeData($p['seller_tier'] ?: 'basic');

jsonResponse(['recommendations' => $products, 'source' => 'popular']);
