<?php
require_once __DIR__ . '/db.php';

function fetchGoogleTokenInfo(string $credential): ?array {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : null;
        }
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function normalizeGoogleProfile(array $tokenInfo): array {
    return [
        'email' => strtolower(trim((string) ($tokenInfo['email'] ?? ''))),
        'google_id' => trim((string) ($tokenInfo['sub'] ?? '')),
        'display_name' => trim((string) ($tokenInfo['name'] ?? '')),
        'picture' => trim((string) ($tokenInfo['picture'] ?? '')),
    ];
}

function makeUniqueGoogleUsername(PDO $pdo, string $email, string $name = ''): string {
    $base = $name !== '' ? $name : strstr($email, '@', true);
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '', $base)));
    if ($base === '') {
        $base = 'campuser';
    }

    $candidate = substr($base, 0, 20);
    $suffix = 1;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");

    while (true) {
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = substr($base, 0, max(6, 20 - strlen((string) $suffix))) . $suffix;
        $suffix++;
    }
}

function storePendingGoogleSignup(array $profile): void {
    $_SESSION['_pending_google_signup'] = [
        'email' => strtolower(trim((string) ($profile['email'] ?? ''))),
        'google_id' => trim((string) ($profile['google_id'] ?? '')),
        'display_name' => trim((string) ($profile['display_name'] ?? '')),
        'picture' => trim((string) ($profile['picture'] ?? '')),
        'created_at' => time(),
    ];
}

function getPendingGoogleSignup(): ?array {
    $payload = $_SESSION['_pending_google_signup'] ?? null;
    if (!is_array($payload)) {
        return null;
    }

    $createdAt = (int) ($payload['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > 900) {
        unset($_SESSION['_pending_google_signup']);
        return null;
    }

    return $payload;
}

function clearPendingGoogleSignup(): void {
    unset($_SESSION['_pending_google_signup']);
}

function findGoogleLinkedUser(PDO $pdo, array $profile): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR LOWER(email) = ? ORDER BY CASE WHEN google_id = ? THEN 0 ELSE 1 END LIMIT 1");
    $stmt->execute([$profile['google_id'], $profile['email'], $profile['google_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function linkGoogleIdentityToUser(PDO $pdo, array $user, array $profile): ?array {
    $update = $pdo->prepare("UPDATE users
        SET google_id = COALESCE(NULLIF(google_id, ''), ?),
            auth_provider = CASE WHEN auth_provider IS NULL OR auth_provider = '' THEN 'google' ELSE auth_provider END,
            google_avatar = CASE WHEN ? <> '' THEN ? ELSE google_avatar END,
            email_verified_at = COALESCE(email_verified_at, NOW())
        WHERE id = ?");
    $update->execute([$profile['google_id'], $profile['picture'], $profile['picture'], $user['id']]);
    return getUser($pdo, (int) $user['id']);
}

function createGoogleMarketplaceUser(PDO $pdo, array $profile, string $role): ?array {
    $role = in_array($role, ['buyer', 'seller', 'admin'], true) ? $role : 'buyer';
    $username = makeUniqueGoogleUsername($pdo, $profile['email'], $profile['display_name']);
    $randomPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $faculty = $role === 'admin' ? 'Administration' : 'Google Sign-In';
    $tier = $role === 'seller' ? 'basic' : 'basic';
    $boolTrue = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'true' : '1';

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, seller_tier, verified, terms_accepted, whatsapp_joined, google_id, auth_provider, google_avatar, email_verified_at)
            VALUES (?, ?, ?, ?, ?, ?, {$boolTrue}, false, false, ?, 'google', ?, NOW())
            RETURNING id");
        $insert->execute([$username, $profile['email'], $randomPassword, $role, $faculty, $tier, $profile['google_id'], $profile['picture'] ?: null]);
        $userId = (int) $insert->fetchColumn();
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, seller_tier, verified, terms_accepted, whatsapp_joined, google_id, auth_provider, google_avatar, email_verified_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, 0, 0, ?, 'google', ?, NOW())");
        $insert->execute([$username, $profile['email'], $randomPassword, $role, $faculty, $tier, $profile['google_id'], $profile['picture'] ?: null]);
        $userId = (int) $pdo->lastInsertId();
    }

    return getUser($pdo, $userId);
}

function startGoogleUserSession(PDO $pdo, array $user, bool $isNewUser = false): void {
    clearPendingGoogleSignup();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    try {
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
    } catch (PDOException $e) {
    }

    if ($isNewUser) {
        setFlashMessage('auth_success', 'Google sign-in is ready. You can finish any profile details later.');
    }

    if (($user['role'] ?? '') === 'admin') {
        redirect('admin/');
    }

    if ($isNewUser && in_array($user['role'] ?? '', ['buyer', 'seller'], true)) {
        redirect('whatsapp_join.php');
    }

    // Existing Google users who haven't joined WhatsApp yet
    if (in_array($user['role'] ?? '', ['buyer', 'seller'], true) && !filter_var($user['whatsapp_joined'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        redirect('whatsapp_join.php');
    }

    redirect('dashboard.php');
}
