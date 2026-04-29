<?php
// ── Authentication ────────────────────────────────────────────────────────────
// This script performs destructive operations (file extraction, .env replacement,
// opcache reset). It must only be callable by an authorised deploy agent.
//
// Read DEPLOY_SECRET from .env (web root) or alwaysdata.env if present.
$deploySecret = '';
foreach (['../.env', '../alwaysdata.env'] as $envFile) {
    $envPath = __DIR__ . '/' . $envFile;
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (strpos($line, 'DEPLOY_SECRET=') === 0) {
                $deploySecret = trim(substr($line, strlen('DEPLOY_SECRET=')), "\"' \t");
                break 2;
            }
        }
    }
}

$provided = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($deploySecret === '' || !hash_equals($deploySecret, $provided)) {
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
