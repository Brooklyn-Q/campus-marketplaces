<?php
// ── Authentication ────────────────────────────────────────────────────────────
$deploySecret = '';
foreach (['.env', 'alwaysdata.env'] as $envFile) {
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
