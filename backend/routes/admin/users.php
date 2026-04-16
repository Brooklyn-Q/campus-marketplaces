<?php
/**
 * Admin User Management
 * GET  /admin/users              — List users
 * PUT  /admin/users/:id/suspend  — Toggle suspend
 * PUT  /admin/users/:id/verify   — Verify user
 * PUT  /admin/users/:id/role     — Change role
 * PUT  /admin/users/:id/tier     — Upgrade tier
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

$userId = is_numeric($param) ? (int) $param : null;
$userAction = $segments[3] ?? '';

// ── LIST USERS ──
if ($method === 'GET' && !$userId) {
    $filter = getQueryParam('filter', 'all');
    $search = getQueryParam('q', '');
    $page = max(1, (int) getQueryParam('page', 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = "role != 'admin'";
    $params = [];

    if ($filter === 'sellers') { $where .= " AND role = 'seller'"; }
    elseif ($filter === 'buyers') { $where .= " AND role = 'buyer'"; }
    elseif ($filter === 'suspended') { $where .= " AND suspended = 1"; }

    if ($search) {
        $where .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, username, email, role, seller_tier, verified, suspended, profile_pic, created_at, last_seen FROM users WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);

    jsonResponse(['users' => $stmt->fetchAll(), 'total' => $total]);
}

// ── SUSPEND/UNSUSPEND ──
elseif ($method === 'PUT' && $userId && $userAction === 'suspend') {
    $stmt = $pdo->prepare("SELECT suspended FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) jsonError('User not found', 404);

    $newVal = $user['suspended'] ? 0 : 1;
    $pdo->prepare("UPDATE users SET suspended = ? WHERE id = ?")->execute([$newVal, $userId]);
    auditLog($pdo, $auth['user_id'], ($newVal ? 'Suspended' : 'Unsuspended') . " user #$userId", 'user', $userId);
    jsonSuccess($newVal ? 'User suspended' : 'User unsuspended');
}

// ── VERIFY ──
elseif ($method === 'PUT' && $userId && $userAction === 'verify') {
    $pdo->prepare("UPDATE users SET verified = 1 WHERE id = ?")->execute([$userId]);
    auditLog($pdo, $auth['user_id'], "Verified user #$userId", 'user', $userId);
    jsonSuccess('User verified');
}

// ── CHANGE ROLE ──
elseif ($method === 'PUT' && $userId && $userAction === 'role') {
    $body = getJsonBody();
    $newRole = $body['role'] ?? '';
    if (!in_array($newRole, ['buyer', 'seller'])) jsonError('Invalid role');

    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $userId]);
    auditLog($pdo, $auth['user_id'], "Changed user #$userId role to $newRole", 'user', $userId);
    jsonSuccess("Role changed to $newRole");
}

// ── UPGRADE TIER ──
elseif ($method === 'PUT' && $userId && $userAction === 'tier') {
    $body = getJsonBody();
    $tier = $body['tier'] ?? 'premium';
    if (!in_array($tier, ['basic', 'pro', 'premium'])) jsonError('Invalid tier');

    $tiers = getAccountTiers($pdo);
    $tierInfo = $tiers[$tier] ?? null;
    $expiresAt = null;

    if ($tierInfo && $tier !== 'basic' && $tierInfo['duration'] !== 'forever') {
        $days = $tierInfo['duration'] === 'weekly' ? 7 : 14;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
    }

    $updateFields = $tier === 'basic' ? "seller_tier = 'basic', tier_expires_at = NULL" : "seller_tier = ?, tier_expires_at = ?";

    if ($tier === 'basic') {
        $pdo->prepare("UPDATE users SET seller_tier = 'basic', tier_expires_at = NULL WHERE id = ?")->execute([$userId]);
    } else {
        $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = ? WHERE id = ?")->execute([$tier, $expiresAt, $userId]);
    }

    auditLog($pdo, $auth['user_id'], "Upgraded user #$userId to $tier tier", 'user', $userId);
    jsonSuccess("User upgraded to $tier");
}

else {
    jsonError('Admin users endpoint not found', 404);
}
