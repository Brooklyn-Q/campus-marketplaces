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

$dir  = __DIR__ . '/';
$zip  = new ZipArchive();
$res  = $zip->open($dir . 'app.zip');

if ($res === true) {
    $zip->extractTo($dir);
    $zip->close();

    if (file_exists($dir . 'alwaysdata.env')) {
        if (file_exists($dir . '.env')) {
            unlink($dir . '.env');
        }
        rename($dir . 'alwaysdata.env', $dir . '.env');
    }

    if (file_exists($dir . 'alwaysdata.htaccess')) {
        if (file_exists($dir . '.htaccess')) {
            unlink($dir . '.htaccess');
        }
        rename($dir . 'alwaysdata.htaccess', $dir . '.htaccess');
    }

    clearstatcache();

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    echo 'SUCCESS';
    unlink($dir . 'app.zip');
} else {
    echo 'FAILED with error code: ' . $res;
}
