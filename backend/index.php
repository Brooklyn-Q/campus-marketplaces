<?php
/**
 * Campus Marketplace REST API — Main Router
 * All requests are routed through this file via .htaccess
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Load environment + config
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/validators.php';
require_once __DIR__ . '/middleware/auth.php';

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if Apache rewritten the route
if (isset($_GET['route'])) {
    $uri = '/' . $_GET['route'];
} else {
    // Strip base path (works in subdirectory or root)
    $uri = $_SERVER['REQUEST_URI'];
    // Automatically detect the script's directory to strip it
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
}
// Strip query string
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}
$uri = $uri ?: '/';

// Remove trailing slash (except root)
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// Parse URI segments
$segments = array_values(array_filter(explode('/', $uri)));

// Get database connection
$pdo = getDbConnection();

// === ROUTE MATCHING ===

try {
    // Prefix: /api/...
    if (($segments[0] ?? '') === 'api') {
        array_shift($segments); // remove 'api'
    }

    $resource = $segments[0] ?? '';
    $action = $segments[1] ?? '';
    $param = $segments[2] ?? '';

    switch ($resource) {
        // ── DEBUG ──
        case 'debug':
            require __DIR__ . '/routes/debug.php';
            break;

        // ── AUTH ──
        case 'auth':
            require __DIR__ . '/routes/auth.php';
            break;

        // ── TEMPORARY ADMIN SETUP ──
        case 'setup':
            require __DIR__ . '/create_admin.php';
            break;

        // ── PRODUCTS ──
        case 'products':
            require __DIR__ . '/routes/products.php';
            break;

        // ── ORDERS ──
        case 'orders':
            require __DIR__ . '/routes/orders.php';
            break;

        // ── MESSAGES ──
        case 'messages':
            require __DIR__ . '/routes/messages.php';
            break;

        // ── REVIEWS ──
        case 'reviews':
            require __DIR__ . '/routes/reviews.php';
            break;

        // ── USERS ──
        case 'users':
            require __DIR__ . '/routes/users.php';
            break;

        // ── PAYMENTS ──
        case 'payments':
            require __DIR__ . '/routes/payments.php';
            break;

        // ── SEARCH ──
        case 'search':
            require __DIR__ . '/routes/search.php';
            break;

        // ── RECOMMENDATIONS ──
        case 'recommendations':
            require __DIR__ . '/routes/recommendations.php';
            break;

        // ── LEADERBOARD ──
        case 'leaderboard':
            require __DIR__ . '/routes/leaderboard.php';
            break;

        // ── ADS ──
        case 'ads':
            require __DIR__ . '/routes/ads.php';
            break;

        // ── ANNOUNCEMENTS ──
        case 'announcements':
            require __DIR__ . '/routes/announcements.php';
            break;

        // ── NOTIFICATIONS ──
        case 'notifications':
            require __DIR__ . '/routes/notifications.php';
            break;

        // ── AI ──
        case 'ai':
            require __DIR__ . '/routes/ai.php';
            break;

        // ── UPLOAD ──
        case 'upload':
            require __DIR__ . '/routes/upload.php';
            break;

        // ── ADMIN ──
        case 'admin':
            $adminResource = $segments[1] ?? '';
            $adminFile = __DIR__ . '/routes/admin/' . $adminResource . '.php';
            if ($adminResource && file_exists($adminFile)) {
                require $adminFile;
            } else {
                require __DIR__ . '/routes/admin/dashboard.php';
            }
            break;

        // ── SETTINGS ──
        case 'settings':
            require __DIR__ . '/routes/settings.php';
            break;

        // ── HEALTH CHECK ──
        case '':
        case 'health':
            jsonResponse(['status' => 'ok', 'service' => 'Campus Marketplace API', 'version' => '2.0.0']);
            break;

        default:
            jsonError('Endpoint not found', 404);
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    jsonError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Server error: ' . $e->getMessage());
    jsonError('Server error: ' . $e->getMessage(), 500);
}
