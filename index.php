<?php
/**
 * Production Routing Bridge
 * This file lives in the project root to bridge requests
 * directly to the backend/ folder for environments like Render.
 */

$uri = $_SERVER['REQUEST_URI'];

// 1. Log incoming requests for debugging (optional)
// error_log("Bridge received URI: " . $uri);

// 2. Routing Logic
// If requested from root or /api, forward to backend/index.php
if ($uri === '/' || strpos($uri, '/api') === 0) {
    // Explicitly include backend entry point
    require_once __DIR__ . '/backend/index.php';
    exit;
}

// 3. Fallback: If it's a direct file access that exists, let it be
// (Though typically Render/Deploy handles static separately)
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // Let server serve file
}

// Default: Forward to backend anyway
require_once __DIR__ . '/backend/index.php';
