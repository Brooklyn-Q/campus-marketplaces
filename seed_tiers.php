<?php
require_once 'c:/xampp/htdocs/marketplace/includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_tiers (
        tier_name VARCHAR(50) PRIMARY KEY,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        duration VARCHAR(50) NOT NULL DEFAULT 'forever',
        product_limit INT NOT NULL DEFAULT 2,
        images_per_product INT NOT NULL DEFAULT 1,
        badge VARCHAR(50) NOT NULL DEFAULT 'blue',
        ads_boost TINYINT(1) NOT NULL DEFAULT 0,
        priority VARCHAR(50) NOT NULL DEFAULT 'normal'
    ) ENGINE=InnoDB");

    $pdo->exec("REPLACE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost, priority) VALUES 
        ('basic', 0.00, 'forever', 2, 1, 'blue', 0, 'normal'),
        ('pro', 10.00, '2_weeks', 5, 2, 'silver', 0, 'normal'),
        ('premium', 20.00, 'weekly', 15, 3, 'gold', 1, 'top')
    ");
    echo "Tiers seeded successfully!";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
