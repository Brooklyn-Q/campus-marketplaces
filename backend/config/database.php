<?php
/**
 * Database connection via PDO
 * Reads credentials from environment file(s)
 *
 * AlwaysData deployment notes:
 * - The deploy script may ship `alwaysdata.env` and/or rename it to `.env` in the web root.
 * - The API lives in `/backend`, so we load env from the web root first, then `/backend/.env` as fallback.
 */

function loadEnv(): void {
    $rootEnv = __DIR__ . '/../../.env';
    $rootAlwaysdataEnv = __DIR__ . '/../../alwaysdata.env';
    $backendEnv = __DIR__ . '/../.env';

    $candidates = [];

    // Prefer AlwaysData env file on non-local HTTP requests when present.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = is_string($host) ? strtolower(trim(explode(':', $host)[0] ?? '')) : '';
    $isCli = PHP_SAPI === 'cli';
    $isLocalHost = $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '[::1]';
    $preferAlwaysdata = !$isCli && !$isLocalHost && is_file($rootAlwaysdataEnv);

    if ($preferAlwaysdata) {
        $candidates[] = $rootAlwaysdataEnv;
    } elseif (is_file($rootEnv)) {
        $candidates[] = $rootEnv;
    } elseif (is_file($rootAlwaysdataEnv)) {
        // Fallback when `.env` is absent (local/dev convenience).
        $candidates[] = $rootAlwaysdataEnv;
    }
    // Backend folder as last resort.
    $candidates[] = $backendEnv;

    foreach ($candidates as $envFile) {
        if (!is_file($envFile)) {
            continue;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

loadEnv();

function env(string $key, $default = '') {
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    return $_ENV[$key] ?? $default;
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
            PDO::ATTR_EMULATE_PREPARES => $driver === 'pgsql',
            PDO::ATTR_PERSISTENT => $driver !== 'pgsql',
        ];

        if ($driver === 'mysql') {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        $pdo = new PDO($dsn, $user, $pass, $options);
        if ($driver === 'pgsql' && defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
            $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
        }
    } catch (PDOException $e) {
        error_log('RESCUE_DB_FAILED: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'error' => 'RESCUE_DATABASE_CONNECTION_ERROR',
            'details' => $e->getMessage()
        ]);
        exit;
    }

    return $pdo;
}
