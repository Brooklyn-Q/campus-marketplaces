<?php
$files = scandir('./');
foreach ($files as $f) {
    if (strpos($f, '\\') !== false) {
        unlink($f);
    }
}
echo 'CLEANED';
?>
