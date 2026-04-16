<?php
/**
 * Seller Middleware — ensures the user is a seller or admin
 */

function requireSeller(PDO $pdo, array $auth): array {
    $role = $auth['role'] ?? '';
    if ($role !== 'seller' && $role !== 'admin') {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['seller', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Seller access required']);
            exit;
        }
    }
    return $auth;
}
