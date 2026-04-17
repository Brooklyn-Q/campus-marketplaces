<?php
$ch = curl_init('http://localhost/marketplace/backend/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'admin', 'password' => 'admin123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);

$data = json_decode($resp, true);
if (!empty($data['token'])) {
    $token = $data['token'];
    
    // Check orders
    $ch2 = curl_init('http://localhost/marketplace/backend/api/orders');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $resp2 = curl_exec($ch2);
    $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    echo "ORDERS ($httpCode): " . $resp2 . "\n\n";

    // Check my products
    $ch3 = curl_init('http://localhost/marketplace/backend/api/products/my');
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $resp3 = curl_exec($ch3);
    $httpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    echo "MY PRODUCTS ($httpCode): " . $resp3 . "\n\n";
    
    // Check admin dashboard
    $ch4 = curl_init('http://localhost/marketplace/backend/api/admin/dashboard');
    curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch4, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $resp4 = curl_exec($ch4);
    $httpCode = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
    echo "ADMIN DASHBOARD ($httpCode): " . substr($resp4, 0, 200) . "...\n\n";

}
