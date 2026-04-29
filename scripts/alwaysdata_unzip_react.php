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

$zip = new ZipArchive;
$res = $zip->open('react.zip');
if ($res === TRUE) {
    if (!is_dir('./public')) { mkdir('./public', 0755, true); }
    $zip->extractTo('./public');
    $zip->close();
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    echo 'React assets extracted successfully.';
    unlink('react.zip');
} else {
    echo 'Failed to extract React assets. Error code: ' . $res;
}
