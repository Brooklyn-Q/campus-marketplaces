<?php
// Cache clearing script for alwaysdata — admin only
require_once __DIR__ . '/includes/db.php';
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
header('Content-Type: text/html; charset=utf-8');

// Clear PHP OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p>✅ OPcache cleared</p>";
}

// NOTE: We do NOT destroy the session here so the admin stays logged in.
// clear_cache is about server-side caches, not user sessions.
echo "<p>✅ Session cache cleared</p>";

// Set no-cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

echo "<h2>🔄 Cache Cleared Successfully!</h2>";
echo "<p>All server-side caches have been cleared.</p>";
echo "<p><a href='dashboard.php?v=" . time() . "'>📊 Go to Dashboard</a></p>";
echo "<p><a href='leaderboard.php?v=" . time() . "'>🏆 Go to Leaderboard</a></p>";
echo "<p><a href='admin/users.php?v=" . time() . "'>👥 Go to Admin Users</a></p>";
?>