<?php
/**
 * ONE-TIME admin password reset + IP throttle clear utility.
 * DELETE THIS FILE after use!
 */

$secret = $_GET['secret'] ?? '';
if ($secret !== 'reset_admin_2026') {
    die('Access denied. Use ?secret=reset_admin_2026');
}

require_once 'includes/db.php';

$new_password = 'Admin@2026';
$email        = 'adminmarketplacecampus@gmail.com';

$hash = password_hash($new_password, PASSWORD_DEFAULT);

// Reset password
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
$stmt->execute([$hash, $email]);
$rows = $stmt->rowCount();

// Clear ALL login_attempts throttle entries so the IP is unblocked
try {
    $pdo->exec("DELETE FROM login_attempts");
    $cleared = true;
} catch (Exception $e) {
    $cleared = false;
}

// Show all admin accounts for verification
$admins = $pdo->query("SELECT id, email, username, role FROM users WHERE role = 'admin'")->fetchAll();

echo "<style>body{font-family:monospace;padding:2rem;}</style>";

if ($rows > 0) {
    echo "<p style='color:green'>✅ Password reset successfully.<br>";
    echo "Email: <strong>" . htmlspecialchars($email) . "</strong><br>";
    echo "New password: <strong>" . htmlspecialchars($new_password) . "</strong></p>";
} else {
    echo "<p style='color:red'>❌ No admin found with that email. See admin accounts below.</p>";
}

echo "<p style='color:" . ($cleared ? 'green' : 'orange') . "'>" . ($cleared ? '✅' : '⚠️') . " Login throttle " . ($cleared ? 'cleared — your IP is unblocked.' : 'could not be cleared.') . "</p>";

echo "<p><strong>Admin accounts in DB:</strong><pre>" . htmlspecialchars(print_r($admins, true)) . "</pre></p>";

echo "<p style='color:red'><strong>DELETE this file from the server now!</strong></p>";
?>
