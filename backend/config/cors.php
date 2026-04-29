<?php
/**
 * CORS configuration for the REST API.
 *
 * - Uses an allowlist (FRONTEND_URL can be a single origin or comma-separated list)
 * - Always allows common localhost dev origins
 * - For preflight (OPTIONS), returns 204 with no body
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
require_once __DIR__ . '/database.php';

if (is_string($origin)) {
    $origin = rtrim($origin, '/');
}

// Build allowed origins (exact matches only).
$allowedOrigins = [];

// FRONTEND_URL can be: "https://site.netlify.app" or "https://a.com,https://b.com"
$envFrontend = (string) env('FRONTEND_URL', '');
if ($envFrontend !== '') {
    foreach (explode(',', $envFrontend) as $entry) {
        $entry = trim($entry);
        if ($entry !== '') {
            $allowedOrigins[] = rtrim($entry, '/');
        }
    }
}

// Local dev defaults
$allowedOrigins = array_merge($allowedOrigins, [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
]);

// Allow same-host origin (useful when frontend and API share the same domain).
$host = $_SERVER['HTTP_HOST'] ?? '';
if (is_string($host) && $host !== '') {
    // Always include both schemes for safety (reverse proxies may terminate TLS upstream).
    $allowedOrigins[] = 'https://' . $host;
    $allowedOrigins[] = 'http://' . $host;

    // If a reverse proxy sets X-Forwarded-Proto, include that exact scheme too.
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && $forwardedProto !== '') {
        $proto = strtolower(trim(explode(',', $forwardedProto)[0] ?? ''));
        if ($proto === 'http' || $proto === 'https') {
            $allowedOrigins[] = $proto . '://' . $host;
        }
    }
}

$allowedOrigins = array_values(array_unique($allowedOrigins));

// CORS headers
header('Vary: Origin');

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
if (!is_string($requestedHeaders) || trim($requestedHeaders) === '') {
    $requestedHeaders = 'Content-Type, Authorization, X-Requested-With';
}
header('Access-Control-Allow-Headers: ' . $requestedHeaders);
header('Access-Control-Max-Age: 86400');

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// All API endpoints return JSON
header('Content-Type: application/json; charset=UTF-8');
