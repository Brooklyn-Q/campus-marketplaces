<?php
require_once 'includes/db.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

$redirectUrl = rtrim($baseUrl, '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>">
    <title>Signing out...</title>
</head>
<body>
    <script>
        try {
            localStorage.removeItem('cm_token');
            sessionStorage.removeItem('cm_token');
        } catch (error) {}
        window.location.replace(<?= json_encode($redirectUrl) ?>);
    </script>
</body>
</html>
