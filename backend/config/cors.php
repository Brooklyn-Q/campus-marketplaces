<?php
/**
 * CORS Configuration
 * Allows the frontend (Netlify) to call the backend API
 */

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://campusmarketplaces.netlify.app',
];

$envFrontend = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? '');
if ($envFrontend) {
    $allowedOrigins[] = rtrim($envFrontend, '/');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// 1. Handle Whitelisted Origins
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} 
// 2. Allow Localhost Development Fallback
elseif (empty($origin) && in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:5173', 'localhost:3000'])) {
    header("Access-Control-Allow-Origin: *");
}
// 3. DO NOT EXIT here for non-CORS requests (like direct hits to /health)
// Just let them through without the Access-Control header unless it's an OPTIONS preflight.

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!in_array($origin, $allowedOrigins)) {
        http_response_code(403);
        exit;
    }
    http_response_code(204);
    exit;
}
