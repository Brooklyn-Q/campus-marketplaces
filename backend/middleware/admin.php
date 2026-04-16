<?php
/**
 * Admin Middleware — ensures the authenticated user is an admin
 */

function requireAdmin(PDO $pdo, array $auth): array {
    if (($auth['role'] ?? '') !== 'admin') {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
    }
    return $auth;
}
