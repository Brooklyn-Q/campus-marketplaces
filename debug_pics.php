<?php
require 'includes/db.php';
$s = $pdo->query('SELECT id, username, profile_pic FROM users LIMIT 10');
foreach ($s as $r) {
    echo $r['id'] . ' | ' . $r['username'] . ' | ' . ($r['profile_pic'] ?: '(none)') . "\n";
}
echo "\n--- Checking columns ---\n";
$cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name LIKE '%pic%' OR column_name LIKE '%photo%' OR column_name LIKE '%avatar%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Image columns: " . implode(', ', $cols) . "\n";
?>