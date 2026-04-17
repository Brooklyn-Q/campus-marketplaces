<?php
/**
 * Authentication Middleware
 * Extracts and validates JWT from Authorization header
 */

function authenticate(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!$header) {
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
            if (isset($requestHeaders['authorization'])) {
                $header = $requestHeaders['authorization'];
            }
        }
    }

    // SECURITY: Do NOT accept tokens from query string - they get logged in server logs
    // Only accept from Authorization header

    if (!$header || !str_starts_with($header, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $token = substr($header, 7);
    $payload = jwtDecode($token);

    if (!$payload || !isset($payload['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }

    return $payload;
}

/**
 * Optional authentication — returns user data if token present, null otherwise
 */
function optionalAuth(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!$header) {
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
            if (isset($requestHeaders['authorization'])) {
                $header = $requestHeaders['authorization'];
            }
        }
    }

    // SECURITY: Do NOT accept tokens from query string - they get logged in server logs

    if (!$header || !str_starts_with($header, 'Bearer ')) {
        return null;
    }

    $token = substr($header, 7);
    return jwtDecode($token);
}
