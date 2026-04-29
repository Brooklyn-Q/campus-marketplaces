<?php
// Standalone maintenance page — no DB dependency, no header.php.
// Reads only the .maintenance file and the PHP session (already started
// by a prior page load if the user is logged in as admin).
if (session_status() === PHP_SESSION_NONE) {
    // Match the same cookie params as db.php so the existing session is
    // resumed correctly (secure flag on HTTPS, httponly, SameSite=Lax).
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    $sessionParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $sessionParams['domain'] ?? '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// If maintenance is off, redirect home
if (!file_exists(__DIR__ . '/.maintenance')) {
    header('Location: /');
    exit;
}

// Admins bypass — check session directly, no DB call needed
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if ($isAdmin) {
    header('Location: /admin/');
    exit;
}

http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — Campus Marketplace</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0f1e;
            color: #fff;
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .card {
            max-width: 480px;
            width: 100%;
        }
        .icon { font-size: 4rem; margin-bottom: 1.5rem; }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -0.03em;
        }
        p {
            color: rgba(255,255,255,0.55);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .badge {
            display: inline-block;
            background: rgba(124,58,237,0.15);
            border: 1px solid rgba(124,58,237,0.4);
            color: #a78bfa;
            padding: 0.4rem 1rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-bottom: 2rem;
        }
        .admin-link {
            display: block;
            margin-top: 2.5rem;
            color: rgba(255,255,255,0.25);
            font-size: 0.78rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .admin-link:hover { color: rgba(255,255,255,0.5); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚧</div>
        <div class="badge">SCHEDULED MAINTENANCE</div>
        <h1>We'll be back shortly</h1>
        <p>Campus Marketplace is currently undergoing maintenance. Our engineers are working hard to get things back online. Check back soon!</p>
        <a href="/login.php" class="admin-link">Admin login →</a>
    </div>
</body>
</html>
