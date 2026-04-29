<?php
// Debug endpoint - shows what headers we're receiving
jsonResponse([
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'NOT SET',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'NOT SET',
    'all_headers' => getallheaders() ?: [],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'server_host' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
]);
