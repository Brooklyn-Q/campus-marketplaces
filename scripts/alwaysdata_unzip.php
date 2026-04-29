<?php
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
