<?php
/**
 * Leaderboard Route
 * GET /leaderboard — Top 10 sellers
 */

if ($method !== 'GET') jsonError('Method not allowed', 405);

    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $intervalSql = $driver === 'pgsql' ? "CURRENT_TIMESTAMP - INTERVAL '1 day'" : "NOW() - INTERVAL 1 DAY";

    $stmt = $pdo->query("
        SELECT u.id, u.username, u.profile_pic, u.department, u.seller_tier, u.verified,
            (SELECT COUNT(*) FROM products WHERE user_id = u.id AND status='approved') as active_listings,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type='sale' AND created_at >= $intervalSql) as sales_today,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type='sale') as lifetime_sales,
            COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type='sale'), 0) as total_earnings
        FROM users u
        WHERE u.role IN ('seller', 'admin')
        ORDER BY sales_today DESC, lifetime_sales DESC, active_listings DESC
        LIMIT 10
    ");
$leaders = $stmt->fetchAll();

foreach ($leaders as &$l) {
    $l['badge'] = getBadgeData($l['seller_tier'] ?: 'basic');
    $l['is_online'] = false; // Would need last_seen check
    if ($l['sales_today'] > 0) {
        $l['status'] = 'trending';
    } elseif ($l['lifetime_sales'] > 10) {
        $l['status'] = 'power_seller';
    } else {
        $l['status'] = 'growing';
    }
}

jsonResponse(['leaders' => $leaders]);
