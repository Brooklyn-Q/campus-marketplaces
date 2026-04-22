<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

/**
 * Poll Notifications API (PostgreSQL Optimized)
 * Fetches unread message and notification counts with throttled heartbeat.
 */

if (!isLoggedIn()) { 
    http_response_code(401); 
    echo json_encode(['error' => 'Unauthorized']); 
    exit; 
}

$me = $_SESSION['user_id'];

try {
    // 1. Bulk Update Delivery Status
    // Marks 'sent' messages as 'delivered' for the sender's UI.
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET delivery_status = 'delivered' 
        WHERE receiver_id = ? AND delivery_status = 'sent'
    ");
    $stmt->execute([$me]);

    // 2. Fetch counts via Subqueries
    // One round-trip to the DB to get both values.
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE) as msg_count,
            (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE) as notif_count
    ");
    $stmt->execute([$me, $me]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Optimized Heartbeat: Update 'last_seen' once every 60 seconds
    $now = time();
    $last_hb = $_SESSION['last_heartbeat'] ?? 0;
    
    if (($now - $last_hb) >= 60) {
        updateLastSeen($pdo, $me);
        $_SESSION['last_heartbeat'] = $now;
    }

    // 4. Final Response
    echo json_encode([
        'unread_messages'      => (int)($counts['msg_count'] ?? 0),
        'unread_notifications' => (int)($counts['notif_count'] ?? 0),
        'status'               => 'success'
    ]);

} catch(Exception $e) {
    error_log("Polling Error [UID $me]: " . $e->getMessage());
    // We don't use http_response_code(500) here to avoid triggering 
    // global error handlers in some JS frameworks for a simple polling blip.
    echo json_encode([
        'status' => 'error',
        'unread_messages' => 0,
        'unread_notifications' => 0
    ]);
}
