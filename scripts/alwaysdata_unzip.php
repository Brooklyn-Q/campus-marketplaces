<?php
// ── Authentication ────────────────────────────────────────────────────────────
// This script performs destructive operations (file extraction, .env replacement,
// opcache reset). It must only be callable by an authorised deploy agent.
$secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
if (!hash_equals('Brooklyn@2005', $secret)) {
    http_response_code(403);
    exit('Access denied.');
}
// ─────────────────────────────────────────────────────────────────────────────

$zip = new ZipArchive();
$res = $zip->open('app.zip');

if ($res === true) {
    $zip->extractTo('./');
    $zip->close();

    if (file_exists('alwaysdata.env')) {
        if (file_exists('.env')) {
            unlink('.env');
        }
        rename('alwaysdata.env', '.env');
    }

    if (file_exists('alwaysdata.htaccess')) {
        if (file_exists('.htaccess')) {
            unlink('.htaccess');
        }
        rename('alwaysdata.htaccess', '.htaccess');
    }

    clearstatcache();

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    echo 'SUCCESS';
    unlink('app.zip');
} else {
    echo 'FAILED with error code: ' . $res;
}
