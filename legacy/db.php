<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$host = get_env_var('DB_HOST', 'localhost');
$dbname = get_env_var('DB_NAME', 'campus_marketplace');
$db_user = get_env_var('DB_USER', 'root');
$db_pass = get_env_var('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Unique constraint initialization moved to migrate.php

} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        $pdo = null;
    } else {
        // PROFESSIONAL LIVE MODE ERROR SCREEN
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance | Campus Marketplace</title><style>body{background:#0a0f1e;color:#fff;font-family:system-ui, -apple-system, sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;overflow:hidden;text-align:center;} .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(20px);padding:3rem;border-radius:24px;border:1px solid rgba(255,255,255,0.1);max-width:400px;box-shadow:0 40px 100px rgba(0,0,0,0.4);} h1{font-size:3rem;margin:0;background:linear-gradient(135deg, #fff 0%, #666 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.05em;} p{color:rgba(255,255,255,0.6);line-height:1.6;margin-top:1rem;font-size:1.1rem;} .dot{height:8px;width:8px;background:#7c3aed;border-radius:50%;display:inline-block;margin-right:8px;box-shadow:0 0 15px #7c3aed;}</style></head><body><div class="glass"><h1>503</h1><p><span class="dot"></span>We are currently optimizing our servers. <br>The Campus Marketplace will be back online shortly.</p></div></body></html>');
    }
}

function get_env_var(string $key, $default = '') {
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
                    // Remove surrounding quotes if they exist
                    $v = trim($v, "\"'");
                    $env[$k] = $v;
                }
            }
        }
    }
    return $env[$key] ?? $default;
}

// ── Helper Functions ──

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
    $path = trim(str_replace('\\', '/', $path));
    
    if ($asset_domains === null) {
        $domains_str = get_env_var('ASSET_DOMAINS', '');
        $asset_domains = $domains_str ? array_filter(array_map('trim', explode(',', $domains_str))) : [];
    }

    // Normalize relative local paths stored in mixed formats such as
    // ../uploads/foo.jpg, ./uploads/foo.jpg, /uploads/foo.jpg, or uploads\foo.jpg.
    $path = preg_replace('#^(?:\./|\../)+#', '', $path);
    
    // If no asset domains are configured, return the local base URL joined with the path
    // Handle cases where templates force 'uploads/' prefix on Cloudinary absolute URLs
    if (strpos($path, 'uploads/http') === 0) {
        return substr($path, 8);
    }

    // Normalize duplicated local-upload prefixes, e.g. uploads/uploads/foo.jpg
    while (strpos($path, 'uploads/uploads/') === 0) {
        $path = substr($path, 8);
    }

    if (strpos($path, '/uploads/') === 0) {
        $path = ltrim($path, '/');
    }

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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
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
        'blue' => 'linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%)',
        default => $color // Support hex codes in DB
    };
    
    $txt = '#fff';
    $icon = match($tier) { 
        'premium' => '⭐ ', 
        'pro' => '⚡ ', 
        default => '✔️ ' 
    };

    return "<span class='badge' style='background:$bg; color:$txt; padding:4px 12px; border-radius:30px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); border:1px solid rgba(255,255,255,0.1);'>{$icon}" . ucfirst($tier) . "</span>";
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

    // Batch frequency check based on 'duration'
    if ($tier !== 'basic' && !empty($u['last_upload_at'])) {
        $duration = $tiers[$tier]['duration'] ?? 'forever';
        $daysNeeded = match($duration) { 'weekly' => 7, '2_weeks' => 14, default => 0 };
        if ($daysNeeded > 0) {
            $last = strtotime($u['last_upload_at']);
            if (time() - $last < ($daysNeeded * 86400)) return false;
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
            $pdo->exec("REPLACE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost, priority) VALUES 
                ('basic', 0.00, 'forever', 2, 1, 'blue', 0, 'normal'),
                ('pro', 10.00, '2_weeks', 5, 2, 'silver', 0, 'normal'),
                ('premium', 20.00, 'weekly', 15, 3, 'gold', 1, 'top')
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

// Update last_seen on each request
if (isLoggedIn() && $pdo) {
    updateLastSeen($pdo, $_SESSION['user_id']);
}
