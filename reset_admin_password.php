<?php
/**
 * ONE-TIME admin password reset utility.
 * DELETE THIS FILE after use!
 */

// Simple secret to prevent accidental public access
$secret = $_GET['secret'] ?? '';
if ($secret !== 'reset_admin_2026') {
    die('Access denied. Use ?secret=reset_admin_2026');
}

require_once 'includes/db.php';

$new_password = 'Admin@2026'; // Change this to what you want
$email        = 'adminmarketplacecampus@gmail.com';

$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
$stmt->execute([$hash, $email]);
$rows = $stmt->rowCount();

if ($rows > 0) {
    echo "<p style='color:green;font-family:monospace'>✅ Admin password reset successfully.<br>";
    echo "Email: <strong>" . htmlspecialchars($email) . "</strong><br>";
    echo "New password: <strong>" . htmlspecialchars($new_password) . "</strong><br>";
    echo "<br><strong style='color:red'>DELETE this file from the server now!</strong></p>";
} else {
    // Try to find what admin accounts exist
    $admins = $pdo->query("SELECT id, email, username, role FROM users WHERE role = 'admin'")->fetchAll();
    echo "<p style='color:red;font-family:monospace'>❌ No admin found with email: " . htmlspecialchars($email) . "<br><br>";
    echo "Admin accounts in DB:<pre>" . htmlspecialchars(print_r($admins, true)) . "</pre></p>";
}
?>
