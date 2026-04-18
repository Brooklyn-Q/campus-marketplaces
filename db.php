<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$host = env('DB_HOST', 'localhost');
$dbname = env('DB_NAME', 'campus_marketplace');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');
$db_port = env('DB_PORT', '3306');
$db_type = env('DB_DRIVER', '') ?: env('DB_TYPE', 'mysql'); // 'mysql' or 'pgsql'

try {
    if ($db_type === 'pgsql' || $db_port == '5432' || $db_port == '6543') {
        $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname";
        $pdo = new PDO($dsn, $user = $db_user, $pass = $db_pass);
    } else {
        $dsn = "mysql:host=$host;port=$db_port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        $pdo = null;
    } else {
        // PROFESSIONAL LIVE MODE ERROR SCREEN
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance | Campus Marketplace</title><style>body{background:#0a0f1e;color:#fff;font-family:system-ui, -apple-system, sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;overflow:hidden;text-align:center;} .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(20px);padding:3rem;border-radius:24px;border:1px solid rgba(255,255,255,0.1);max-width:400px;box-shadow:0 40px 100px rgba(0,0,0,0.4);} h1{font-size:3rem;margin:0;background:linear-gradient(135deg, #fff 0%, #666 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.05em;} p{color:rgba(255,255,255,0.6);line-height:1.6;margin-top:1rem;font-size:1.1rem;} .dot{height:8px;width:8px;background:#0071e3;border-radius:50%;display:inline-block;margin-right:8px;box-shadow:0 0 15px #0071e3;}</style></head><body><div class="glass"><h1>503</h1><p><span class="dot"></span>We are currently optimizing our servers. <br>The Campus Marketplace will be back online shortly.</p></div></body></html>');
    }
}

// ── CSRF Protection ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

function check_csrf(): void {
    $token = $_POST['csrf_token'] ?? null;
    
    // Check JSON input if POST is empty
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    }

    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']));
        }
        http_response_code(403);
        die('CSRF token validation failed. Please return, refresh the page, and try again.');
    }
}

function env(string $key, $default = '') {
    // 1. Check system environment (e.g. Render/Netlify)
    $val = getenv($key);
    if ($val !== false) return $val;

    // 2. Check local .env file
    static $env = null;
    if ($env === null) {
        $env = [];
        $path = __DIR__ . '/.env';
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    $v = trim($v, "\"'");
                    $env[$k] = $v;
                }
            }
        }
    }
    return $env[$key] ?? $default;
}

// ── Helper Functions ──

function sqlBool(bool $val, PDO $pdo): string {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
        ? ($val ? 'true' : 'false') 
        : ($val ? '1' : '0');
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isSeller(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'seller';
}

function isBuyer(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'buyer';
}

function getBaseUrl(): string {
    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_path);
    if ($dir === '/' || $dir === '\\') return '/';
    return rtrim($dir, '/\\') . '/';
}

$baseUrl = getBaseUrl();

function getAssetUrl(string $path): string {
    global $baseUrl;
    static $asset_domains = null;
    
    if ($asset_domains === null) {
        $domains_str = env('ASSET_DOMAINS', '');
        $asset_domains = $domains_str ? array_filter(array_map('trim', explode(',', $domains_str))) : [];
    }
    
    // If no asset domains are configured, return the local base URL joined with the path
    if (empty($asset_domains)) {
        if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) return $path;
        return $baseUrl . ltrim($path, '/');
    }
    
    // Domain sharding logic: pick a domain based on path hash
    // We use crc32 to consistently map the same asset to the same domain (better for caching)
    $index = abs(crc32($path)) % count($asset_domains);
    $domain = $asset_domains[$index];
    
    // Ensure domain has protocol
    if (strpos($domain, 'http') !== 0 && strpos($domain, '//') !== 0) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $protocol . $domain;
    }
    
    // To support sharding from project root, we need to handle the marketplace/ prefix if it's there
    // If the path already has the base URL, we might need to strip it if shards point to root
    // But usually it's safer to just ltrim the path and append to domain.
    return rtrim($domain, '/') . '/' . ltrim($path, '/');
}


function redirect(string $url): void {
    global $baseUrl;
    // If not an absolute URL, prefix with base
    if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
        $url = $baseUrl . $url;
    }
    header("Location: $url");
    exit();
}

function generateReferralCode(int $length = 8): string {
    return strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
}

function generateRef(string $prefix = 'TX'): string {
    return $prefix . '_' . strtoupper(uniqid());
}

function getUser(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function getUnreadCount(PDO $pdo, int $userId): int {
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function updateLastSeen(PDO $pdo, int $userId): void {
    try {
        $pdo->prepare("UPDATE users SET last_seen = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $userId]);
    } catch (PDOException $e) {
        // Frequent heartbeat writes can hit lock waits; keep requests non-blocking.
        $errorCode = (int)($e->errorInfo[1] ?? 0);
        if ($errorCode !== 1205 && $errorCode !== 1213) {
            throw $e;
        }
    }
}

function auditLog(PDO $pdo, int $adminId, string $action, string $targetType = '', int $targetId = 0): void {
    $pdo->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id) VALUES (?,?,?,?)")
        ->execute([$adminId, $action, $targetType, $targetId]);
}

function getProductImages(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$productId]);
    return $stmt->fetchAll() ?: [];
}

function getSellerProductCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getBadgeHtml(PDO $pdo, string $tier): string {
    $tier = strtolower($tier);
    try {
        $stmt = $pdo->prepare("SELECT badge FROM account_tiers WHERE tier_name = ?");
        $stmt->execute([$tier]);
        $color = strtolower((string)$stmt->fetchColumn());
    } catch(Exception $e) { $color = null; }
    
    // Default Fallback
    if(!$color) {
        $color = match($tier) { 
            'premium' => 'gold', 
            'pro' => 'silver', 
            default => 'blue' 
        };
    }
    
    $bg = match($color) { 
        'gold' => 'linear-gradient(135deg, #ff9f0a 0%, #d4af37 100%)', 
        'silver' => 'linear-gradient(135deg, #8b939a 0%, #5d6d7e 100%)', 
        'blue' => 'linear-gradient(135deg, #0071e3 0%, #0056b3 100%)',
        default => $color // Support hex codes in DB
    };
    
    $txt = '#fff';
    $icon = match($tier) { 
        'premium' => '⭐ ', 
        'pro' => '⚡ ', 
        default => '✔️ ' 
    };

    return "<span class='badge' style='background:".htmlspecialchars($bg, ENT_QUOTES, 'UTF-8')."; color:".htmlspecialchars($txt, ENT_QUOTES, 'UTF-8')."; padding:4px 12px; border-radius:30px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); border:1px solid rgba(255,255,255,0.1);'>{$icon}" . ucfirst($tier) . "</span>";
}


function canAddProduct($pdo, $userId) {
    if (!$userId) return false;
    $u = getUser($pdo, $userId);
    if (!$u) return false;
    $tier = $u['seller_tier'] ?: 'basic';
    
    $tiers = getAccountTiers($pdo);
    $limit = $tiers[$tier]['product_limit'] ?? 2;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status != 'deleted'");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() >= $limit) return false;

    // Batch frequency check based on 'duration' (stored as months)
    if ($tier !== 'basic' && !empty($u['last_upload_at'])) {
        $duration = $tiers[$tier]['duration'] ?? '1';
        $monthsNeeded = is_numeric($duration) ? (int)$duration : 0;
        if ($monthsNeeded > 0) {
            $last = strtotime($u['last_upload_at']);
            // Using 30 days as a standard month for restriction logic
            if (time() - $last < ($monthsNeeded * 30 * 86400)) return false;
        }
    }
    
    return true;
}

function getMaxImages($pdo, $userId) {
    $u = getUser($pdo, $userId);
    if (!$u) return 1;
    $tier = $u['seller_tier'] ?: 'basic';
    
    $tiers = getAccountTiers($pdo);
    return (int)($tiers[$tier]['images_per_product'] ?? 1);
}

/**
 * Checks if user must submit a review before browsing.
 * Rule: 10. Reviews required before further browsing.
 */
function hasUnreviewedOrders($pdo, $userId) {
    if (!$userId) return false;
    $s = $pdo->prepare("SELECT COUNT(*) FROM orders o 
        LEFT JOIN reviews r ON o.product_id = r.product_id AND r.user_id = o.buyer_id
        WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL");
    $s->execute([$userId]);
    return ((int)$s->fetchColumn() > 0);
}

// System-wide tables (settings, orders, tiers, profile_edit_requests) 
// are now managed in migrate.php to prevent excessive server load.


function getAccountTiers(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT * FROM account_tiers");
        $tiers = [];
        while($row = $stmt->fetch()) {
            $tiers[$row['tier_name']] = $row;
        }

        if(empty($tiers)) {
            // Auto-seed if somehow empty during a query
            $boolF = sqlBool(false, $pdo);
            $boolT = sqlBool(true, $pdo);
            $pdo->exec("INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                ('basic', 0.00, '0', 2, 1, 'blue', $boolF),
                ('pro', 10.00, '1', 5, 2, 'silver', $boolF),
                ('premium', 20.00, '1', 15, 3, 'gold', $boolT)
                ON CONFLICT (tier_name) DO NOTHING
            ");
            // Retry fetch
            $stmt = $pdo->query("SELECT * FROM account_tiers");
            while($row = $stmt->fetch()) { $tiers[$row['tier_name']] = $row; }
        }
        return $tiers;
    } catch(Exception $e) { return []; }
}

function getSetting(PDO $pdo, string $key, $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? (string)$val : (string)$default;
    } catch(Exception $e) { return (string)$default; }
}



function getAvgRating(PDO $pdo, int $productId): float {
    $stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    return (float)$stmt->fetchColumn() ?: 0.0;
}

// ────────────────────────────────────────────────────────────────────────
// SECURITY: Rate Limiting and Logging Functions
// ────────────────────────────────────────────────────────────────────────

/**
 * Check if an IP/user has exceeded rate limit for login attempts
 * @param string $ip Client IP address
 * @param int $maxAttempts Maximum failed attempts before blocking
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'attempts' => int, 'cooldown' => int]
 */
function checkRateLimit(string $ip, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);

    $data = ['attempts' => 0, 'first_attempt' => time()];

    if (file_exists($cache_file)) {
        $cached = @json_decode(file_get_contents($cache_file), true);
        if ($cached && (time() - $cached['first_attempt']) < $windowSeconds) {
            $data = $cached;
        } else {
            @unlink($cache_file);
        }
    }

    $allowed = $data['attempts'] < $maxAttempts;
    $cooldown = max(0, $windowSeconds - (time() - $data['first_attempt']));

    return [
        'allowed' => $allowed,
        'attempts' => $data['attempts'],
        'cooldown' => $cooldown
    ];
}

/**
 * Record a failed login attempt for rate limiting
 */
function recordFailedLogin(string $ip): void {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);

    $data = ['attempts' => 0, 'first_attempt' => time()];

    if (file_exists($cache_file)) {
        $cached = @json_decode(file_get_contents($cache_file), true);
        if ($cached && (time() - $cached['first_attempt']) < 900) {
            $data = $cached;
        }
    }

    $data['attempts']++;
    @file_put_contents($cache_file, json_encode($data));
}

/**
 * Clear rate limit for successful login
 */
function clearRateLimit(string $ip): void {
    $key = "login_attempts_$ip";
    $cache_file = sys_get_temp_dir() . '/marketplace_' . md5($key);
    @unlink($cache_file);
}

/**
 * Log security events for monitoring
 */
function logSecurityEvent(PDO $pdo, string $eventType, string $description, ?int $userId = null, ?string $ipAddress = null): void {
    try {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $pdo->prepare("INSERT INTO security_logs (event_type, description, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$eventType, $description, $userId, $ip]);
    } catch (Exception $e) {
        // Log to file if database fails
        error_log("[SECURITY] $eventType: $description (User: $userId, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ")");
    }
}

/**
 * Log payment verification attempt
 */
function logPaymentVerification(PDO $pdo, int $userId, string $reference, string $status, ?string $error = null): void {
    try {
        $pdo->prepare("INSERT INTO payment_verification_logs (user_id, reference, status, error_message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$userId, $reference, $status, $error, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        error_log("[PAYMENT] Verification failed for ref $reference: " . ($error ?? 'unknown'));
    }
}

// ────────────────────────────────────────────────────────────────────────

// Update last_seen on each request
if (isLoggedIn() && $pdo) {
    updateLastSeen($pdo, $_SESSION['user_id']);
}
