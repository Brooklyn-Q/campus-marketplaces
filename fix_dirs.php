<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = __DIR__;
$dirs = ['config', 'helpers', 'middleware', 'routes', 'routes/admin', 'models', 'services', 'migrations'];

// Create directories
foreach ($dirs as $d) {
    $path = "$base/$d";
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "Created dir: $d\n";
    }
}

// Find files with backslash in name and move them
$files = scandir($base);
foreach ($files as $f) {
    if (strpos($f, '\\') !== false) {
        $proper = str_replace('\\', '/', $f);
        $dir = dirname($proper);
        if (!is_dir("$base/$dir")) {
            mkdir("$base/$dir", 0755, true);
            echo "Created dir: $dir\n";
        }
        rename("$base/$f", "$base/$proper");
        echo "Moved: $f -> $proper\n";
    }
}

// Also re-extract the zip properly now that dirs exist
if (file_exists("$base/backend.zip")) {
    $zip = new ZipArchive;
    if ($zip->open("$base/backend.zip") === TRUE) {
        $zip->extractTo("$base/");
        $zip->close();
        echo "\nRe-extracted zip successfully.\n";
    }
}

// Verify critical files
$check = ['config/cors.php', 'config/database.php', 'config/jwt.php', 'helpers/functions.php', 'helpers/validators.php', 'middleware/auth.php'];
echo "\n--- Verification ---\n";
foreach ($check as $c) {
    echo "$c: " . (file_exists("$base/$c") ? "OK" : "MISSING") . "\n";
}
echo "\nDone!\n";
?>
