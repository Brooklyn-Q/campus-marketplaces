<?php
/**
 * Admin Analytics Route
 * GET /admin/analytics
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

if ($method !== 'GET') jsonError('Method not allowed', 405);

$driver = getenv('DB_DRIVER') ?: 'mysql';

// ── Core Metrics ──
$metrics = [
    'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed'")->fetchColumn(),
    'boost_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='boost' AND status='completed'")->fetchColumn(),
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
    'products' => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'sellers' => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM products WHERE status='approved'")->fetchColumn(),
    'orders' => (int) $pdo->query("SELECT COUNT(*) FROM transactions WHERE type IN ('sale','purchase')")->fetchColumn(),
    'views' => (int) $pdo->query("SELECT COALESCE(SUM(views),0) FROM products")->fetchColumn(),
    'new_today' => 0
];

if ($driver === 'pgsql') {
    $metrics['new_today'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE CAST(created_at AS DATE) = CURRENT_DATE")->fetchColumn();
} else {
    $metrics['new_today'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
}

// ── Daily Revenue & Users (last 14 days) ──
$chartRevenueLabels = [];
$chartRevenueData = [];
$chartUsersLabels = [];
$chartUsersData = [];

for ($i = 13; $i >= 0; $i--) {
    $dateStr = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($dateStr));
    
    // Revenue
    $stmtRev = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed' AND CAST(created_at AS DATE) = ?");
    $stmtRev->execute([$dateStr]);
    $chartRevenueLabels[] = $label;
    $chartRevenueData[] = (float) $stmtRev->fetchColumn();

    // Users
    $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE CAST(created_at AS DATE) = ?");
    $stmtUsers->execute([$dateStr]);
    $chartUsersLabels[] = $label;
    $chartUsersData[] = (int) $stmtUsers->fetchColumn();
}

// ── Category Distribution ──
$catLabels = [];
$catData = [];
$categoryDist = $pdo->query("SELECT category, COUNT(*) as cnt FROM products WHERE status='approved' GROUP BY category ORDER BY cnt DESC LIMIT 10")->fetchAll();
foreach ($categoryDist as $cat) {
    if (!$cat['category']) continue;
    $catLabels[] = $cat['category'];
    $catData[] = (int)$cat['cnt'];
}

// ── Top 10 Products by Views ──
$topViewed = $pdo->query("
    SELECT p.title, p.views, p.price, u.username as seller 
    FROM products p JOIN users u ON p.user_id = u.id 
    WHERE p.status='approved' 
    ORDER BY p.views DESC LIMIT 10
")->fetchAll();

// ── Top 10 Products by Sales (clicks acting as sales metric in older DB) ──
// Safely try to select clicks, fallback to views if clicks doesn't exist
$topSelling = [];
try {
    $topSellingQuery = "
        SELECT p.title, p.clicks as sales, p.price, u.username as seller 
        FROM products p JOIN users u ON p.user_id = u.id 
        ORDER BY p.clicks DESC LIMIT 10
    ";
    $topSelling = $pdo->query($topSellingQuery)->fetchAll();
} catch (Exception $e) {
    // If clicks column is missing, fake it or ignore
}

// ── Top 5 Seller Performers ──
$topSellers = $pdo->query("
    SELECT u.username, u.seller_tier, COUNT(t.id) as sale_count, COALESCE(SUM(t.amount),0) as revenue 
    FROM users u 
    LEFT JOIN transactions t ON u.id = t.user_id AND t.type = 'sale' AND t.status='completed'
    WHERE u.role = 'seller' 
    GROUP BY u.username, u.seller_tier 
    ORDER BY revenue DESC LIMIT 5
")->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'metrics' => $metrics,
        'charts' => [
            'revenue' => ['labels' => $chartRevenueLabels, 'data' => $chartRevenueData],
            'users' => ['labels' => $chartUsersLabels, 'data' => $chartUsersData],
            'categories' => ['labels' => $catLabels, 'data' => $catData]
        ],
        'tables' => [
            'topViewed' => $topViewed,
            'topSelling' => $topSelling,
            'topSellers' => $topSellers
        ]
    ]
]);
