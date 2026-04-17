<?php
/**
 * CORS Configuration
 * Allows the frontend (Netlify) to call the backend API
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// DIAGNOSTIC: Allow ALL Origins recorded for production rescue
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://campusmarketplaces.netlify.app',
];

$envFrontend = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? '');
if ($envFrontend) {
    $allowedOrigins[] = rtrim($envFrontend, '/');
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // During rescue, we allow all preflights
    http_response_code(204);
    exit;
}
