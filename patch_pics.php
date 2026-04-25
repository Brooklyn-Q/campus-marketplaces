<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT id, username, profile_pic FROM users WHERE profile_pic IS NOT NULL");
foreach ($stmt as $r) {
    if (strpos($r['profile_pic'], 'localhost') !== false) {
        $pdo->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?")->execute([$r['id']]);
        echo "Fixed user ID {$r['id']}\n";
    }
}
echo "Done.\n";
?>
