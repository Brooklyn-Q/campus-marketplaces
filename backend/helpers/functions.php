<?php
/**
 * Global Helper Functions
 */

// ── JSON RESPONSES ──

function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function jsonSuccess(string $message, array $extra = []): void {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $extra));
}

// ── REQUEST HELPERS ──

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getRequestMethod(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function getQueryParam(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

// ── DATABASE HELPERS ──

function getUser(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        unset($user['password']); // Never expose password
    }
    return $user ?: null;
}

function getUserPublic(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT id, username, profile_pic, seller_tier, verified, department, 
               faculty, level, bio, vacation_mode, last_seen, created_at,
               (SELECT COUNT(*) FROM products WHERE user_id = u.id AND status = 'approved') as product_count,
               (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.user_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type = 'sale' AND status = 'completed') as total_sales
        FROM users u WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $user['is_online'] = $user['last_seen'] && (time() - strtotime($user['last_seen'])) < 300;
    }
    return $user ?: null;
}

function updateLastSeen(PDO $pdo, int $userId): void {
    try {
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);
    } catch (PDOException $e) {
        $errorCode = (int)($e->errorInfo[1] ?? 0);
        if ($errorCode !== 1205 && $errorCode !== 1213) {
            throw $e;
        }
    }
}

function auditLog(PDO $pdo, int $adminId, string $action, string $targetType = '', int $targetId = 0): void {
    $pdo->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id) VALUES (?, ?, ?, ?)")
        ->execute([$adminId, $action, $targetType, $targetId]);
}

function getSetting(PDO $pdo, string $key, $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: (string) $default;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value]);
}



function getAccountTiers(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC");
        $rows = $stmt->fetchAll();
        $tiers = [];
        foreach ($rows as $r) {
            $tiers[$r['tier_name']] = $r;
        }
        if (empty($tiers)) {
            // Auto-seed
            $pdo->exec("INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
                ('basic', 0, 'forever', 2, 1, '#0071e3', 0),
                ('pro', 10, '2_weeks', 5, 2, '#8e8e93', 0),
                ('premium', 20, 'weekly', 15, 3, '#ff9f0a', 1)");
            return getAccountTiers($pdo);
        }
        return $tiers;
    } catch (PDOException $e) {
        return [];
    }
}

function canAddProduct(PDO $pdo, int $userId): bool {
    $user = getUser($pdo, $userId);
    if (!$user) return false;

    $tier = $user['seller_tier'] ?: 'basic';
    $tiers = getAccountTiers($pdo);
    $limit = (int)($tiers[$tier]['product_limit'] ?? 2);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status IN ('approved', 'pending', 'paused')");
    $stmt->execute([$userId]);
    $count = (int) $stmt->fetchColumn();

    return $count < $limit;
}

function getMaxImages(PDO $pdo, int $userId): int {
    $user = getUser($pdo, $userId);
    $tier = $user['seller_tier'] ?? 'basic';
    $tiers = getAccountTiers($pdo);
    return (int)($tiers[$tier]['images_per_product'] ?? 1);
}

function hasUnreviewedOrders(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders o
        LEFT JOIN reviews r ON r.product_id = o.product_id AND r.user_id = ?
        WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL
    ");
    $stmt->execute([$userId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getUnreadMessageCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function generateReferralCode(): string {
    return 'CM' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function generateRef(string $prefix = 'TX'): string {
    return $prefix . '_' . time() . '_' . strtoupper(substr(md5(uniqid()), 0, 6));
}

function formatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (str_starts_with($phone, '0') && strlen($phone) === 10) {
        $phone = '+233' . substr($phone, 1);
    }
    return $phone;
}

function getBadgeData(string $tier): array {
    $badges = [
        'basic' => ['label' => 'Basic', 'color' => '#0071e3', 'bg' => 'rgba(0,113,227,0.1)'],
        'pro' => ['label' => 'Pro', 'color' => '#8e8e93', 'bg' => 'rgba(142,142,147,0.12)'],
        'premium' => ['label' => 'Premium', 'color' => '#ff9f0a', 'bg' => 'rgba(255,159,10,0.12)'],
    ];
    return $badges[$tier] ?? $badges['basic'];
}

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function getAvgRating(PDO $pdo, int $productId): float {
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    return round((float) $stmt->fetchColumn(), 1);
}
