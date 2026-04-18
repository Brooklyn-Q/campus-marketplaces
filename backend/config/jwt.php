<?php
/**
 * JWT Helper Functions
 * Encode and decode JSON Web Tokens for API auth
 */

function jwtEncode(array $payload): string {
    $secret = env('JWT_SECRET');
    // SECURITY: Fail fast if JWT_SECRET not configured or still uses default
    if (empty($secret) || $secret === 'campus_marketplace_secret_key_change_me') {
        if (function_exists('jsonError')) jsonError('FATAL: JWT_SECRET not configured. Please set a strong secret in your environment settings.', 500);
        die(json_encode(['error' => 'JWT_SECRET not configured']));
    }
    $expiry = (int) env('JWT_EXPIRY', 604800); // 7 days

    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

    $payload['iat'] = time();
    $payload['exp'] = time() + $expiry;
    $payloadEncoded = base64url_encode(json_encode($payload));

    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", $secret, true)
    );

    return "$header.$payloadEncoded.$signature";
}

function jwtDecode(string $token): ?array {
    $secret = env('JWT_SECRET');
    // SECURITY: Fail fast if JWT_SECRET not configured or still uses default
    if (empty($secret) || $secret === 'campus_marketplace_secret_key_change_me') {
        throw new Exception('FATAL: JWT_SECRET not configured. Set a strong secret in .env file');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;

    // Verify signature
    $validSignature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", $secret, true)
    );

    if (!hash_equals($validSignature, $signature)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data) return null;

    // Check expiration
    if (isset($data['exp']) && $data['exp'] < time()) return null;

    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
