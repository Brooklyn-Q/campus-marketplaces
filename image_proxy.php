<?php
require_once 'includes/db.php';

$src = trim((string) ($_GET['src'] ?? ''));
if ($src === '' || !preg_match('#^https?://#i', $src)) {
    http_response_code(400);
    exit('Invalid image source');
}

$parsed = parse_url($src);
$scheme = strtolower((string) ($parsed['scheme'] ?? ''));
$host = strtolower((string) ($parsed['host'] ?? ''));
if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
    http_response_code(400);
    exit('Invalid image source');
}

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
        CURLOPT_SSL_VERIFYPEER => true,
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
    exit('Unsupported media type');
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');
echo $body;
