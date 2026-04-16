<?php
/**
 * CORS Configuration
 * Allows the frontend (Vercel) to call the backend API
 */

$allowedOrigins = [
    'http://localhost:3000',       // Local Next.js dev
    'http://localhost:5173',       // Local Vite dev
];

// Add production frontend URL from env
$envFrontend = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? '');
if ($envFrontend) {
    $allowedOrigins[] = rtrim($envFrontend, '/');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (!$envFrontend) {
    // Dev: allow all origins if no FRONTEND_URL is set
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
