<?php
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $sessionParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $sessionParams['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

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
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $db_type === 'pgsql');
    if (($db_type === 'pgsql' || $db_port == '5432' || $db_port == '6543') && defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
        $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
    }

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

function get_env_var(string $key, $default = '') {
    return env($key, $default);
}

// ── Helper Functions ──

function sqlBool(bool $val, PDO $pdo): string {
    global $db_type;
    $isPgsql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql')
               || ($db_type === 'pgsql');
    return $isPgsql
        ? ($val ? 'TRUE' : 'FALSE')
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

function getPrimaryAdminId(PDO $pdo): int {
    static $cachedAdminId = null;

    if ($cachedAdminId !== null) {
        return $cachedAdminId;
    }

    try {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $adminId = (int) ($stmt ? $stmt->fetchColumn() : 0);
        $cachedAdminId = $adminId > 0 ? $adminId : 0;
    } catch (PDOException $e) {
        $cachedAdminId = 0;
    }

    return $cachedAdminId;
}

function getBaseUrl(): string {
    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_path);
    if ($dir === '/' || $dir === '\\') return '/';
    return rtrim($dir, '/\\') . '/';
}

$baseUrl = getBaseUrl();

function getSpaUrl(string $path = '/', array $params = []): string {
    global $baseUrl;

    $normalizedBase = rtrim($baseUrl, '/');
    $normalizedPath = '/' . ltrim($path, '/');
    $query = $params ? '?' . http_build_query($params) : '';

    if ($normalizedPath === '/' && $query === '') {
        return $normalizedBase === '' ? '/' : $normalizedBase . '/';
    }

    $base = $normalizedBase === '' ? '' : $normalizedBase;
    return $base . '/#' . $normalizedPath . $query;
}

function getAssetUrl(string $path): string {
    global $baseUrl;
    static $asset_domains = null;
    $path = trim(str_replace('\\', '/', $path));
    
    if ($asset_domains === null) {
        $domains_str = env('ASSET_DOMAINS', '');
        $asset_domains = $domains_str ? array_filter(array_map('trim', explode(',', $domains_str))) : [];
    }

    // Normalize relative local paths stored in mixed formats such as
    // ../uploads/foo.jpg, ./uploads/foo.jpg, /uploads/foo.jpg, or uploads\foo.jpg.
    $path = preg_replace('#^(?:\./|\../)+#', '', $path);
    
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

function setFlashMessage(string $key, string $message): void {
    $_SESSION['_flash'][$key] = $message;
}

function getFlashMessage(string $key): string {
    $message = $_SESSION['_flash'][$key] ?? '';
    unset($_SESSION['_flash'][$key]);
    return (string) $message;
}

function getAppUrl(): string {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $configured = rtrim((string) env('APP_URL', ''), '/');
    if ($configured !== '') {
        return $cached = $configured;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    global $baseUrl;
    $suffix = rtrim($baseUrl, '/');

    return $cached = $scheme . '://' . $host . $suffix;
}

function googleSignInEnabled(): bool {
    return trim((string) env('GOOGLE_CLIENT_ID', '')) !== '';
}

function runSchemaStatement(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('Feature schema statement failed: ' . $e->getMessage());
    }
}

function ensureFeatureSupportSchema(PDO $pdo): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(191)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(32)");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar TEXT");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at TIMESTAMP NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications BOOLEAN NOT NULL DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS browser_notifications BOOLEAN NOT NULL DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL");
        runSchemaStatement($pdo, "CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id ON users (google_id)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector VARCHAR(32) NOT NULL UNIQUE,
            token_hash VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_reset_tokens (user_id)");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_password_resets_expires_at ON password_reset_tokens (expires_at)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_notifications_user_created ON notifications (user_id, created_at DESC)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id SERIAL PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_security_logs_created_at ON security_logs (created_at DESC)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id SERIAL PRIMARY KEY,
            user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "CREATE INDEX IF NOT EXISTS idx_payment_verification_logs_reference ON payment_verification_logs (reference)");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS ad_placements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_path TEXT DEFAULT '',
            link_url TEXT DEFAULT '#',
            placement VARCHAR(50) DEFAULT 'homepage',
            is_active BOOLEAN DEFAULT TRUE,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_path TEXT DEFAULT ''");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS link_url TEXT DEFAULT '#'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS placement VARCHAR(50) DEFAULT 'homepage'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS impressions INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN image_path TYPE TEXT");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN link_url TYPE TEXT");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT ''");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ALTER COLUMN image_url TYPE TEXT");
        runSchemaStatement($pdo, "UPDATE ad_placements SET image_path = COALESCE(NULLIF(image_path, ''), image_url, '')");
    } else {
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(191) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider VARCHAR(32) NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at DATETIME NULL");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) NOT NULL DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS browser_notifications TINYINT(1) NOT NULL DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL");
        runSchemaStatement($pdo, "CREATE UNIQUE INDEX idx_users_google_id ON users (google_id)");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector VARCHAR(32) NOT NULL UNIQUE,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_resets_user_id (user_id),
            INDEX idx_password_resets_expires_at (expires_at)
        ) ENGINE=InnoDB");

        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user_created (user_id, created_at)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(120) DEFAULT NULL");
        runSchemaStatement($pdo, "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) DEFAULT NULL");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS security_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            user_id INT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_security_logs_created_at (created_at)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS payment_verification_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_payment_verification_logs_reference (reference)
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "CREATE TABLE IF NOT EXISTS ad_placements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_path TEXT NULL,
            link_url TEXT NULL,
            placement VARCHAR(50) DEFAULT 'homepage',
            is_active TINYINT(1) DEFAULT 1,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_path TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS link_url TEXT NULL");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS placement VARCHAR(50) DEFAULT 'homepage'");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS impressions INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0");
        runSchemaStatement($pdo, "ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS image_url TEXT NULL");
        runSchemaStatement($pdo, "UPDATE ad_placements SET image_path = COALESCE(NULLIF(image_path, ''), image_url, '')");
    }

    $ready = true;
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensureFeatureSupportSchema($pdo);
}

function notificationTitleFor(string $type): string {
    return match ($type) {
        'new_message' => 'New message',
        'new_order', 'order_received' => 'New order',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'Order update',
        'admin_alert', 'dispute' => 'Admin alert',
        'password_reset' => 'Password reset',
        default => 'Campus Marketplace',
    };
}

function notificationLinkFor(string $type, ?int $referenceId = null): string {
    return match ($type) {
        'new_message' => $referenceId ? 'chat.php?user=' . $referenceId : 'chat.php',
        'new_order', 'order_received' => 'dashboard.php#seller_orders',
        'order_update', 'order_accepted', 'order_rejected', 'order_cancelled', 'seller_confirmed', 'buyer_confirmed' => 'dashboard.php#buyer_orders',
        'admin_alert', 'dispute' => 'admin/',
        default => 'dashboard.php',
    };
}

function sendMarketplaceEmail(string $to, string $subject, string $html, string $plainText = ''): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $host = parse_url(getAppUrl(), PHP_URL_HOST) ?: 'campusmarketplace.local';
    $fromAddress = (string) env('MAIL_FROM_ADDRESS', 'no-reply@' . $host);
    $fromName = (string) env('MAIL_FROM_NAME', 'Campus Marketplace');

    if ($plainText === '') {
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $html))));
    }

    $boundary = 'cm-' . bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . $fromName . ' <' . $fromAddress . '>';
    $headers[] = 'Reply-To: ' . $fromAddress;
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plainText . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function createNotification(PDO $pdo, int $userId, string $type, string $message, ?int $referenceId = null, array $options = []): void {
    if ($userId <= 0) {
        return;
    }

    ensureFeatureSupportSchema($pdo);

    $title = $options['title'] ?? notificationTitleFor($type);
    $linkUrl = $options['link_url'] ?? notificationLinkFor($type, $referenceId);

    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $title, $message, $linkUrl, $referenceId]);

    $shouldEmail = array_key_exists('email', $options) ? (bool) $options['email'] : true;
    if (!$shouldEmail) {
        return;
    }

    $userStmt = $pdo->prepare("SELECT email, username, email_notifications FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $targetUser = $userStmt->fetch();

    if (!$targetUser || empty($targetUser['email']) || !filter_var($targetUser['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }

    if (isset($targetUser['email_notifications']) && !filter_var($targetUser['email_notifications'], FILTER_VALIDATE_BOOLEAN)) {
        return;
    }

    $absoluteLink = rtrim(getAppUrl(), '/') . '/' . ltrim($linkUrl, '/');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeLink = htmlspecialchars($absoluteLink, ENT_QUOTES, 'UTF-8');

    $html = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111827\">
        <h2 style=\"margin:0 0 12px;color:#0071e3\">{$safeTitle}</h2>
        <p>Hello " . htmlspecialchars($targetUser['username'] ?? 'there', ENT_QUOTES, 'UTF-8') . ",</p>
        <p>{$safeMessage}</p>
        <p><a href=\"{$safeLink}\" style=\"display:inline-block;padding:10px 18px;background:#0071e3;color:#fff;text-decoration:none;border-radius:999px;font-weight:700\">Open Campus Marketplace</a></p>
        <p style=\"font-size:12px;color:#6b7280\">You are receiving this because activity happened on your Campus Marketplace account.</p>
    </div>";

    sendMarketplaceEmail($targetUser['email'], $title . ' | Campus Marketplace', $html);
}

function createMessageNotification(PDO $pdo, int $receiverId, int $senderId, string $messagePreview = ''): void {
    $sender = getUser($pdo, $senderId);
    $senderName = $sender['username'] ?? 'Someone';
    $preview = $messagePreview !== '' ? mb_strimwidth($messagePreview, 0, 90, '…') : 'Open the chat to read it.';
    createNotification(
        $pdo,
        $receiverId,
        'new_message',
        $senderName . ' sent you a message: ' . $preview,
        $senderId,
        [
            'title' => 'New message from ' . $senderName,
            'link_url' => 'chat.php?user=' . $senderId,
        ]
    );
}

function issuePasswordResetToken(PDO $pdo, int $userId): array {
    ensureFeatureSupportSchema($pdo);

    $selector = bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < ?")
        ->execute([$userId, date('Y-m-d H:i:s')]);

    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $selector, $tokenHash, $expiresAt]);

    return ['selector' => $selector, 'token' => $token, 'expires_at' => $expiresAt];
}

function findPasswordResetToken(PDO $pdo, string $selector): ?array {
    ensureFeatureSupportSchema($pdo);

    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE selector = ? LIMIT 1");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function consumePasswordResetToken(PDO $pdo, string $selector): void {
    $pdo->prepare("UPDATE password_reset_tokens SET used_at = ? WHERE selector = ?")
        ->execute([date('Y-m-d H:i:s'), $selector]);
}

if ($pdo instanceof PDO) {
    ensureFeatureSupportSchema($pdo);
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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = ?");
    $stmt->execute([$userId, 0]);
    return (int)$stmt->fetchColumn();
}

function getUnreadMessageCount(PDO $pdo, int $userId): int {
    return getUnreadCount($pdo, $userId);
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    ensureFeatureSupportSchema($pdo);
    $bool = sqlBool(false, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = $bool");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
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
    if ($adminId <= 0) {
        error_log("auditLog skipped for unauthenticated event: " . $action);
        return;
    }

    try {
        $pdo->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id) VALUES (?,?,?,?)")
            ->execute([$adminId, $action, $targetType, $targetId]);
    } catch (PDOException $e) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '23503' || $sqlState === '23000') {
            error_log("auditLog skipped due to missing admin reference #{$adminId}: " . $e->getMessage());
            return;
        }
        throw $e;
    }
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
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                $pdo->exec("INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0.00, '0', 2, 1, 'blue', $boolF),
                    ('pro', 10.00, '1', 5, 2, 'silver', $boolF),
                    ('premium', 20.00, '1', 15, 3, 'gold', $boolT)
                    ON CONFLICT (tier_name) DO NOTHING
                ");
            } else {
                $pdo->exec("INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                    ('basic', 0.00, '0', 2, 1, 'blue', $boolF),
                    ('pro', 10.00, '1', 5, 2, 'silver', $boolF),
                    ('premium', 20.00, '1', 15, 3, 'gold', $boolT)
                ");
            }

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
