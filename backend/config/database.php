<?php
/**
 * Database connection via PDO
 * Reads credentials from .env file
 */

function loadEnv(): void {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        $value = trim($value, '"\'');

        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv();

function env(string $key, $default = '') {
    return getenv($key) ?: ($_ENV[$key] ?? $default);
}

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $driver = env('DB_DRIVER', 'mysql');
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
    $name = env('DB_NAME', 'campus_marketplace');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$name";
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    }

    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode([
            'error' => 'Database connection failed',
            'details' => $e->getMessage() // TEMPORARILY show raw error for rescue
        ]);
        exit;
    }

    return $pdo;
}
