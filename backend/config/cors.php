<?php
/**
 * CORS Configuration
 * Allows the frontend (Netlify) to call the backend API
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://campusmarketplaces.netlify.app',
];

$envFrontend = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? '');
if ($envFrontend) {
    $allowedOrigins[] = rtrim($envFrontend, '/');
}

$allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));

if ($origin && in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . rtrim($origin, '/'));
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else {
    // Reject requests from non-whitelisted origins
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin && !in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
        http_response_code(403);
        exit;
    }

    http_response_code(204);
    exit;
}
