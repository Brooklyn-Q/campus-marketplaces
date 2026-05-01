<?php
require_once __DIR__ . '/db.php';

// ── Google JWT local verification ─────────────────────────────────────────────
// Verifies a Google ID token (RS256 JWT) entirely locally using Google's
// published JWK public keys. No round-trip to tokeninfo — faster, more
// reliable, and not bypassable by network timing attacks.
// Public keys are cached in the PHP session for up to 1 hour.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetch Google's JWK public keys, with session-level cache.
 */
function _fetchGooglePublicKeys(): ?array
{
    $cacheKey = '_google_jwk_cache';
    $cached = $_SESSION[$cacheKey] ?? null;
    if (is_array($cached) && !empty($cached['keys']) && (time() - (int)($cached['fetched_at'] ?? 0)) < 3600) {
        return $cached['keys'];
    }

    $jwkUrl = 'https://www.googleapis.com/oauth2/v3/certs';
    $body = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($jwkUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) $body = false;
    }

    if ($body === false) {
        $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
        $body = @file_get_contents($jwkUrl, false, $ctx);
    }

    if ($body === false) return null;

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['keys'])) return null;

    // Index by kid for fast lookup
    $keys = [];
    foreach ($data['keys'] as $k) {
        if (!empty($k['kid'])) $keys[$k['kid']] = $k;
    }

    $_SESSION[$cacheKey] = ['keys' => $keys, 'fetched_at' => time()];
    return $keys;
}

/**
 * Convert a JWK RSA public key entry into a PEM string PHP can use.
 */
function _jwkToPem(array $jwk): ?string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        return null;
    }

    $decode = fn(string $b64) => base64_decode(strtr($b64, '-_', '+/'));

    $modulus  = $decode($jwk['n']);
    $exponent = $decode($jwk['e']);

    // Build ASN.1 DER for RSAPublicKey { modulus INTEGER, publicExponent INTEGER }
    $encodeLen = function(int $len): string {
        if ($len < 128) return chr($len);
        $bytes = '';
        $tmp = $len;
        while ($tmp > 0) { $bytes = chr($tmp & 0xFF) . $bytes; $tmp >>= 8; }
        return chr(0x80 | strlen($bytes)) . $bytes;
    };

    $encodeInt = function(string $raw) use ($encodeLen): string {
        // Prepend 0x00 if high bit set (avoid negative interpretation)
        if (ord($raw[0]) > 0x7F) $raw = "\x00" . $raw;
        return "\x02" . $encodeLen(strlen($raw)) . $raw;
    };

    $rsaSeq  = $encodeInt($modulus) . $encodeInt($exponent);
    $rsaSeq  = "\x30" . $encodeLen(strlen($rsaSeq)) . $rsaSeq;

    // Wrap in SubjectPublicKeyInfo with RSA OID
    $oid     = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $bitStr  = "\x03" . $encodeLen(strlen($rsaSeq) + 1) . "\x00" . $rsaSeq;
    $spki    = "\x30" . $encodeLen(strlen($oid) + strlen($bitStr)) . $oid . $bitStr;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64) . "-----END PUBLIC KEY-----";
}

/**
 * Decode and locally verify a Google ID token (RS256 JWT).
 * Returns the payload array on success, null on any failure.
 */
function fetchGoogleTokenInfo(string $credential): ?array
{
    $parts = explode('.', $credential);
    if (count($parts) !== 3) return null;

    [$headerB64, $payloadB64, $sigB64] = $parts;

    $header  = json_decode(base64_decode(strtr($headerB64,  '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

    if (!is_array($header) || !is_array($payload)) return null;

    // Must be RS256
    if (($header['alg'] ?? '') !== 'RS256') return null;

    $kid = $header['kid'] ?? '';
    $keys = _fetchGooglePublicKeys();
    if (!$keys) return null;

    // Try exact kid match first, then fall back to trying all keys
    $candidates = $kid && isset($keys[$kid]) ? [$keys[$kid]] : array_values($keys);

    $signedData = $headerB64 . '.' . $payloadB64;
    $signature  = base64_decode(strtr($sigB64, '-_', '+/'));
    $verified   = false;

    foreach ($candidates as $jwk) {
        $pem = _jwkToPem($jwk);
        if (!$pem) continue;
        $pubKey = openssl_pkey_get_public($pem);
        if (!$pubKey) continue;
        if (openssl_verify($signedData, $signature, $pubKey, OPENSSL_ALGO_SHA256) === 1) {
            $verified = true;
            break;
        }
    }

    if (!$verified) return null;

    // Validate standard claims
    $now = time();
    if (empty($payload['exp']) || $payload['exp'] < $now)           return null; // expired
    if (empty($payload['iat']) || $payload['iat'] > $now + 60)      return null; // issued in future
    if (($payload['iss'] ?? '') !== 'https://accounts.google.com'
        && ($payload['iss'] ?? '') !== 'accounts.google.com')        return null; // wrong issuer

    return $payload;
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
    $stmt = $pdo->prepare("SELECT id, role, username, email, suspended, whatsapp_joined, terms_accepted, google_id, auth_provider, google_avatar, profile_pic, email_verified_at, totp_enabled, admin_totp_enabled FROM users WHERE google_id = ? OR LOWER(email) = ? ORDER BY CASE WHEN google_id = ? THEN 0 ELSE 1 END LIMIT 1");
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
    $isAdmin = (($user['role'] ?? '') === 'admin');

    // ── Enforce 2FA for Google Users ──
    if (!$isNewUser) {
        // 1. Admin 2FA
        if ($isAdmin) {
            $adminTotpEnabled = filter_var($user['admin_totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($adminTotpEnabled) {
                ensure_admin_2fa_schema($pdo);
                session_regenerate_id(true);
                $_SESSION['pending_admin_2fa_user_id'] = (int) $user['id'];
                $_SESSION['pending_admin_2fa_username'] = (string) $user['username'];
                $_SESSION['pending_admin_2fa_ip'] = get_login_client_ip();
                redirect('admin/verify_2fa.php');
            }
        }

        // 2. User 2FA
        $userTotpEnabled = filter_var($user['totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($userTotpEnabled) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_username'] = (string) $user['username'];
            $_SESSION['pending_2fa_ip'] = get_login_client_ip();
            $_SESSION['pending_2fa_role'] = (string) $user['role'];
            redirect('verify_2fa.php');
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // Register session (Phase 3)
    registerUserSession($pdo, (int)$user['id']);

    try {
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
    } catch (PDOException $e) {
    }

    if ($isNewUser) {
        setFlashMessage('auth_success', 'Google sign-in is ready. You can finish any profile details later.');
    }

    if ($isAdmin) {
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
