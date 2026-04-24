<?php
/**
 * Admin Dashboard Route
 * GET /admin/dashboard — Stats + overview data
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

if ($method !== 'GET') jsonError('Method not allowed', 405);

$stats = [
    'total_users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
    'sellers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'seller'")->fetchColumn(),
    'buyers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn(),
    'active_listings' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status='approved'")->fetchColumn(),
    'pending_products' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status='pending'")->fetchColumn(),
    'deletion_requests' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status='deletion_requested'")->fetchColumn(),
    'total_transactions' => (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'total_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed'")->fetchColumn(),
    'premium_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='premium' AND status='completed'")->fetchColumn(),
    'boost_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='boost' AND status='completed'")->fetchColumn(),
    'sale_revenue' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='sale' AND status='completed'")->fetchColumn(),
    'total_orders' => 0,
    'pending_discounts' => 0,
    'pending_profiles' => 0,
    'pending_vacations' => 0,
    'open_disputes' => 0,
];

try { $stats['total_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); } catch(Exception $e) {}
try { $stats['pending_discounts'] = (int) $pdo->query("SELECT COUNT(*) FROM discount_requests WHERE status='pending'")->fetchColumn(); } catch(Exception $e) {}
try { $stats['pending_profiles'] = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM profile_edit_requests WHERE status='pending'")->fetchColumn(); } catch(Exception $e) {}
try { $stats['pending_vacations'] = (int) $pdo->query("SELECT COUNT(*) FROM vacation_requests WHERE status='pending'")->fetchColumn(); } catch(Exception $e) {}
try { $stats['open_disputes'] = (int) $pdo->query("SELECT COUNT(*) FROM disputes WHERE status='open'")->fetchColumn(); } catch(Exception $e) {}

// Top sellers
$topSellers = $pdo->query("
    SELECT u.id, u.username, u.seller_tier, u.profile_pic,
        COALESCE(SUM(t.amount),0) as revenue
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id AND t.type = 'sale'
    WHERE u.role = 'seller'
    GROUP BY u.id ORDER BY revenue DESC LIMIT 5
")->fetchAll();

// Pending products
$pendingProducts = $pdo->query("
    SELECT p.id, p.title, p.price, p.created_at, u.username as seller_name, u.id as seller_id
    FROM products p JOIN users u ON p.user_id = u.id
    WHERE p.status='pending' ORDER BY p.created_at ASC LIMIT 10
")->fetchAll();

// Pending discounts
$pendingDiscounts = [];
try {
    $pendingDiscounts = $pdo->query("
        SELECT dr.*, p.title as product_name, u.username as seller_name
        FROM discount_requests dr
        JOIN products p ON dr.product_id = p.id
        JOIN users u ON dr.seller_id = u.id
        WHERE dr.status = 'pending' ORDER BY dr.created_at DESC
    ")->fetchAll();
} catch(Exception $e) {}

// Open disputes
$openDisputes = [];
try {
    $openDisputes = $pdo->query("
        SELECT d.*, b.username as complainant_name, t.username as target_name
        FROM disputes d
        JOIN users b ON d.complainant_id = b.id
        JOIN users t ON d.target_id = t.id
        WHERE d.status='open' ORDER BY d.created_at DESC
    ")->fetchAll();
} catch(Exception $e) {}

// Profile edit requests grouped by user
$profileRequests = [];
try {
    $pe = $pdo->query("
        SELECT per.*, u.username, u.profile_pic
        FROM profile_edit_requests per
        JOIN users u ON per.user_id = u.id
        WHERE per.status='pending'
        ORDER BY per.user_id, per.created_at DESC
    ")->fetchAll();
    foreach ($pe as $r) {
        if (!isset($profileRequests[$r['user_id']])) {
            $profileRequests[$r['user_id']] = ['username' => $r['username'], 'profile_pic' => $r['profile_pic'], 'edits' => []];
        }
        $profileRequests[$r['user_id']]['edits'][] = $r;
    }
} catch(Exception $e) {}

// Vacation requests
$vacationRequests = [];
try {
    $vacationRequests = $pdo->query("SELECT v.*, u.username FROM vacation_requests v JOIN users u ON v.seller_id = u.id WHERE v.status='pending' ORDER BY v.created_at DESC")->fetchAll();
} catch(Exception $e) {}

// Recent orders
$recentOrders = [];
try {
    $recentOrders = $pdo->query("
        SELECT o.*, p.title as product_name, b.username as buyer_name, s.username as seller_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users b ON o.buyer_id = b.id
        JOIN users s ON o.seller_id = s.id
        ORDER BY o.created_at DESC LIMIT 50
    ")->fetchAll();
} catch(Exception $e) {}

// Active announcements
$boolActive = sqlBool(true, $pdo);
$announcements = $pdo->query("SELECT * FROM announcements WHERE is_active = $boolActive ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Account tiers
$tiers = getAccountTiers($pdo);

jsonResponse([
    'stats' => $stats,
    'top_sellers' => $topSellers,
    'pending_products' => $pendingProducts,
    'pending_discounts' => $pendingDiscounts,
    'open_disputes' => $openDisputes,
    'profile_requests' => $profileRequests,
    'vacation_requests' => $vacationRequests,
    'recent_orders' => $recentOrders,
    'announcements' => $announcements,
    'tiers' => $tiers,
]);
