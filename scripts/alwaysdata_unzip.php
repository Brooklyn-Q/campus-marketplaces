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
