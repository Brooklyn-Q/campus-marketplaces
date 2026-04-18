<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    $tables = ['products', 'users', 'product_images'];
    $report = ['driver' => $driver, 'tables' => []];

    foreach ($tables as $table) {
        $report['tables'][$table] = [];
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ?");
            $stmt->execute([$table]);
        } else {
            $stmt = $pdo->prepare("DESCRIBE $table");
            $stmt->execute();
        }
        $report['tables'][$table] = $stmt->fetchAll();
    }

    echo json_encode($report, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
