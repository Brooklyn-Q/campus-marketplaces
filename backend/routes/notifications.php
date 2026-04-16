<?php
/**
 * Notification Routes
 * GET /notifications — List user's notifications
 * PUT /notifications/read — Mark all as read
 */

$auth = authenticate();

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$auth['user_id']]);
    jsonResponse([
        'notifications' => $stmt->fetchAll(),
        'unread_count' => getUnreadNotificationCount($pdo, $auth['user_id']),
    ]);
}

elseif ($method === 'PUT' && $action === 'read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$auth['user_id']]);
    jsonSuccess('All notifications marked as read');
}

else {
    jsonError('Notification endpoint not found', 404);
}
