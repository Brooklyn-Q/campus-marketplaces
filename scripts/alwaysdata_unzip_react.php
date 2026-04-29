<?php
// ── Authentication ────────────────────────────────────────────────────────────
// DEPLOY_SECRET is injected at upload time by deploy_to_alwaysdata.bat
// (the placeholder %%DEPLOY_SECRET%% is replaced before this file is uploaded)
$deploySecret = '%%DEPLOY_SECRET%%';

$provided = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($deploySecret === '' || !hash_equals($deploySecret, $provided)) {
    http_response_code(403);
    exit('Access denied.');
}
// ─────────────────────────────────────────────────────────────────────────────

$dir = __DIR__ . '/';
$zip = new ZipArchive;
$res = $zip->open($dir . 'react.zip');
if ($res === TRUE) {
    $publicDir = $dir . 'public';
    if (!is_dir($publicDir)) { mkdir($publicDir, 0755, true); }
    $zip->extractTo($publicDir);
    $zip->close();
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    echo 'SUCCESS';
    unlink($dir . 'react.zip');
} else {
    echo 'Failed to extract React assets. Error code: ' . $res;
}
