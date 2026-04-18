<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    
    $username = 'superadmin';
    $email = 'admin@campusmarketplaces.com';
    $password = 'CampusAdmin2026!'; // Ensure this matches front-end complexity requirements
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Admin account already exists. Use the login page.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, faculty, verified, seller_tier) 
        VALUES (?, ?, ?, 'admin', 'Administration', true, 'premium')
    ");
    $stmt->execute([$username, $email, $hashed]);

    echo json_encode([
        'status' => 'success', 
        'message' => 'Super Admin account created successfully!',
        'credentials' => [
            'username_or_email' => $email,
            'password' => $password
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
