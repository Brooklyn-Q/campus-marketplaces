<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$me = $_SESSION['user_id'];

// 1. Mark all pending messages bound for this user as 'delivered' (since they are online polling)
try {
    $pdo->prepare("UPDATE messages SET delivery_status='delivered' WHERE receiver_id=? AND delivery_status='sent'")->execute([$me]);
} catch(Exception $e) {}

// 2. Count unread messages
$unreadMsgCount = getUnreadCount($pdo, $me);

// 3. Count unread notifications
$unreadNotifCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$me]);
    $unreadNotifCount = (int)$stmt->fetchColumn();
} catch(Exception $e) {}

// 4. Update last seen to keep online status active globally
updateLastSeen($pdo, $me);

echo json_encode([
    'unread_messages' => $unreadMsgCount,
    'unread_notifications' => $unreadNotifCount
]);
