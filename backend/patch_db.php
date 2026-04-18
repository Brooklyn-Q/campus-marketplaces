<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $results = [];

    // Columns to add to 'products'
    $prodColumns = [
        'promo_tag' => 'VARCHAR(50) DEFAULT NULL',
        'delivery_method' => "VARCHAR(20) DEFAULT 'Pickup'",
        'payment_agreement' => "VARCHAR(50) DEFAULT 'Pay on delivery'",
        'is_deleted' => "BOOLEAN DEFAULT false", // PostgreSQL specific
        'original_price' => "DECIMAL(10,2) DEFAULT NULL"
    ];

    if ($driver !== 'pgsql') {
        $prodColumns['is_deleted'] = "TINYINT(1) DEFAULT 0";
    }

    foreach ($prodColumns as $col => $type) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN $col $type");
            $results[] = "Added $col to products";
        } catch (Exception $e) {
            $results[] = "Skipped $col on products (already exists or error: " . $e->getMessage() . ")";
        }
    }

    // Columns to add to 'users'
    $userColumns = [
        'last_upload_at' => "TIMESTAMP DEFAULT NULL",
        'vacation_mode' => "BOOLEAN DEFAULT false"
    ];

    if ($driver !== 'pgsql') {
        $userColumns['vacation_mode'] = "TINYINT(1) DEFAULT 0";
    }

    foreach ($userColumns as $col => $type) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
            $results[] = "Added $col to users";
        } catch (Exception $e) {
            $results[] = "Skipped $col on users (already exists or error: " . $e->getMessage() . ")";
        }
    }

    // Ensure product_images exists
    if ($driver === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id SERIAL PRIMARY KEY,
            product_id INT NOT NULL,
            image_path TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    }
    $results[] = "Checked/Created product_images table";

    echo json_encode(['status' => 'success', 'results' => $results], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
