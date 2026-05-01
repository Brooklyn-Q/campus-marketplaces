<?php
require_once 'includes/db.php';

// ── Domain allowlist ──────────────────────────────────────────────────────────
$ALLOWED_HOSTS = [
    'res.cloudinary.com',
    'api.cloudinary.com',
    'cloudinary.com',
    'lh3.googleusercontent.com',
    'lh4.googleusercontent.com',
    'lh5.googleusercontent.com',
    'lh6.googleusercontent.com',
    'storage.googleapis.com',
    'placehold.co',
    'campusmarketplace.alwaysdata.net',
    'www.campusmarketplace.alwaysdata.net'
];

// Relaxed check for Cloudinary and AlwaysData subdomains
$hostAllowed = false;
foreach ($ALLOWED_HOSTS as $allowed) {
    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        $hostAllowed = true;
        break;
    }
}
if (strpos($host, 'cloudinary.com') !== false || strpos($host, 'alwaysdata.net') !== false) {
    $hostAllowed = true;
}

// Also allow any Cloudinary subdomain (res-1, res-2, etc.)
// Allowlist check logic below handles suffix matching.

$supabaseRef = env('SUPABASE_PROJECT_REF', '');
if ($supabaseRef !== '') {
    $ALLOWED_HOSTS[] = $supabaseRef . '.supabase.co';
    $ALLOWED_HOSTS[] = $supabaseRef . '.storage.supabase.co';
}

$appUrl = getAppUrl();
$appHost = parse_url($appUrl, PHP_URL_HOST);
if ($appHost) {
    $ALLOWED_HOSTS[] = $appHost;
}
// ─────────────────────────────────────────────────────────────────────────────

$src = trim((string) ($_GET['src'] ?? ''));
if ($src === '' || !preg_match('#^https?://#i', $src)) {
    http_response_code(400);
    exit('Invalid image source');
}

$parsed = parse_url($src);
$scheme = strtolower((string) ($parsed['scheme'] ?? ''));
$host   = strtolower((string) ($parsed['host'] ?? ''));

if (!in_array($scheme, ['http', 'https']) || $host === '') {
    http_response_code(400);
    exit('Invalid image source');
}

// ── SSRF protection ─────────────────────
if (filter_var($host, FILTER_VALIDATE_IP)) {
    http_response_code(403);
    exit('Access denied');
}

// ── Allowlist check ─────────────────────
$hostAllowed = false;
foreach ($ALLOWED_HOSTS as $allowed) {
    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        $hostAllowed = true;
        break;
    }
}
if (!$hostAllowed) {
    http_response_code(403);
    exit('Host not allowed');
}
// ─────────────────────────────────────────────────────────────────────────────

$statusCode = 0;
$contentType = '';
$body = false;

if (function_exists('curl_init')) {
    $ch = curl_init($src);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'CampusMarketplaceImageProxy/1.0',
        CURLOPT_SSL_VERIFYPEER => false, // Set to true in production
        CURLOPT_HTTPHEADER => ['Accept: image/*'],
    ]);
    $body = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header' => "Accept: image/*\r\nUser-Agent: CampusMarketplaceImageProxy/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    $body = @file_get_contents($src, false, $context);
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $m)) {
                $statusCode = (int) $m[1];
            } elseif (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, 13));
            }
        }
    }
}

if ($body === false || $statusCode >= 400 || $statusCode === 0) {
    http_response_code(502);
    exit('Unable to fetch image');
}

if ($contentType === '' || stripos($contentType, 'image/') !== 0) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $contentType = (string) $finfo->buffer($body);
}

if (stripos($contentType, 'image/') !== 0) {
    http_response_code(415);
    exit('Unsupported content type');
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($body));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *'); // Essential for CORS
echo $body;
