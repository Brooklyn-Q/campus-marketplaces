<?php
require_once 'includes/db.php';

if (php_sapi_name() !== 'cli' && !isAdmin()) {
    die("Unauthorized");
}

try {
    $pdo->exec("ALTER TABLE account_tiers ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) DEFAULT NULL");
    echo "Migration successful: original_price added to account_tiers.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration already applied or column exists.";
    } else {
        echo "Migration failed: " . $e->getMessage();
    }
}
?>
