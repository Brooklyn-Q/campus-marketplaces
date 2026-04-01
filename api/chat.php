<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$action = $_GET['action'] ?? '';
$me = $_SESSION['user_id'];
try {
    // Migration helper: safe column check for older MySQL versions
    $cols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('attachment_url', $cols)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_url VARCHAR(500) DEFAULT NULL");
    }
    if (!in_array('message_type', $cols)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN message_type ENUM('text','image','video','audio') DEFAULT 'text'");
    } else {
        $pdo->exec("ALTER TABLE messages MODIFY COLUMN message_type ENUM('text','image','video','audio') DEFAULT 'text'");
    }
    // Rule: message can be null if there is an attachment
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN message TEXT NULL");
} catch(Exception $e) {}

if ($action === 'get') {
    $user2 = (int)($_GET['user'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT m.*, u.username as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC");
        $stmt->execute([$me, $user2, $user2, $me]);
        $messages = $stmt->fetchAll();
    } catch(PDOException $p) {
        // Fallback for missing columns if migration is still pending in some sessions
        $stmt = $pdo->prepare("SELECT m.id, m.sender_id, m.receiver_id, m.message, m.is_read, m.created_at, u.username as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC");
        $stmt->execute([$me, $user2, $user2, $me]);
        $messages = $stmt->fetchAll();
    }
 
    // Mark as read and seen
    $pdo->prepare("UPDATE messages SET is_read = 1, delivery_status = 'seen' WHERE sender_id = ? AND receiver_id = ?")->execute([$user2, $me]);
 
    echo json_encode($messages);

} elseif ($action === 'send') {
    $receiver = 0; $message = ''; $attachment_url = null; $message_type = 'text';

    if (!empty($_FILES['attachment'])) {
        $receiver = (int)($_POST['receiver'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $file = $_FILES['attachment'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'Upload error: ' . $file['error'];
            if ($file['error'] === 1 || $file['error'] === 2) $err = 'File is too large (server limit).';
            echo json_encode(['success' => false, 'error' => $err]); exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_images = ['jpg','jpeg','png','gif','webp'];
        $allowed_videos = ['mp4','webm','mov'];
        $allowed_audio  = ['mp3','wav','m4a','ogg'];
        
        if (in_array($ext, array_merge($allowed_images, $allowed_videos, $allowed_audio))) {
            $newName = 'chat_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], '../uploads/' . $newName)) {
                $attachment_url = $newName;
                // Auto-detect voice notes by prefix
                if (strpos($file['name'], 'voice_note_') === 0) {
                    $message_type = 'audio';
                } elseif (in_array($ext, $allowed_videos)) {
                    $message_type = 'video';
                } elseif (in_array($ext, $allowed_audio)) {
                    $message_type = 'audio';
                } else {
                    $message_type = 'image';
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
            exit;
        }
    } else {
        // Handle text messages whether sent via JSON or FormData
        $json = json_decode(file_get_contents('php://input'), true);
        $receiver = (int)(is_array($json) ? ($json['receiver'] ?? ($_POST['receiver'] ?? 0)) : ($_POST['receiver'] ?? 0));
        $message = trim(is_array($json) ? ($json['message'] ?? ($_POST['message'] ?? '')) : ($_POST['message'] ?? ''));
    }

    if ($receiver && ($message || $attachment_url)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, attachment_url, message_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$me, $receiver, $message ?: '', $attachment_url, $message_type]);
            echo json_encode(['success' => true]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data. Missing receiver, message, or attachment.']);
    }
} elseif ($action === 'check_user') {
    // Real-time availability check
    $field = $_GET['field'] ?? '';
    $value = trim($_GET['value'] ?? '');
    if (in_array($field, ['username', 'email']) && $value) {
        $sql = ($field === 'email') ? "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?)" : "SELECT COUNT(*) FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value]);
        echo json_encode(['taken' => $stmt->fetchColumn() > 0]);
    } else {
        echo json_encode(['error' => 'Invalid']);
    }
}
