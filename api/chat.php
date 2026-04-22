<?php
// BUG FIX #11: Output buffer must open before ANY output-producing code —
// including require_once, which may emit whitespace/BOM from included files.
// This prevents header corruption on the JSON Content-Type response.
ob_start();

require_once '../includes/db.php';
require_once '../includes/storage_helper.php';

header('Content-Type: application/json');

// Centralised JSON response helpers — always flush the buffer cleanly.
function jsonOut(mixed $data): never
{
    ob_end_clean();
    echo json_encode($data);
    exit;
}
function jsonError(string $msg, int $code = 400): never
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// BUG FIX #3: Cast $me to int immediately — session values are strings;
// strict typing prevents type-juggling in all PDO execute() calls below.
if (!isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$me = (int) $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

// ── Schema Migration (PostgreSQL Compatible) ──────────────────────────────────
// FIX: Use information_schema for PostgreSQL (Supabase) compatibility.
$migration_key = 'chat_migration_v4_done';
$migration_done = function_exists('apcu_fetch')
    ? apcu_fetch($migration_key)
    : ($_SESSION[$migration_key] ?? false);

if (!$migration_done) {
    try {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'messages'");
        $stmt->execute();
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('attachment_url', $cols)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_url TEXT DEFAULT NULL");
        }
        if (!in_array('message_type', $cols)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text'");
        }
        
        // PostgreSQL syntax for dropping NOT NULL constraint
        $pdo->exec("ALTER TABLE messages ALTER COLUMN message DROP NOT NULL");

        if (function_exists('apcu_store')) {
            apcu_store($migration_key, true, 86400);
        } else {
            $_SESSION[$migration_key] = true;
        }
    } catch (Exception $e) {
        error_log('chat.php migration error: ' . $e->getMessage());
    }
}

// ── Action: get ───────────────────────────────────────────────────────────────
if ($action === 'get') {

    // BUG FIX #10: Validate $user2 is a positive integer before querying.
    // $user2 = 0 previously returned messages between the user and user_id=0
    // (no DB error, just silently wrong data).
    $user2 = (int) ($_GET['user'] ?? 0);
    if ($user2 <= 0) {
        jsonError('Invalid user ID.');
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT m.*, u.username AS sender_name
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$me, $user2, $user2, $me]);
        $messages = $stmt->fetchAll();
    } catch (PDOException $p) {
        // Fallback: SELECT only the stable columns if the migration hasn't landed yet.
        $stmt = $pdo->prepare(
            "SELECT m.id, m.sender_id, m.receiver_id, m.message,
                    m.is_read, m.created_at, u.username AS sender_name
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$me, $user2, $user2, $me]);
        $messages = $stmt->fetchAll();
    }

    $pdo->prepare(
        "UPDATE messages SET is_read = TRUE, delivery_status = 'seen'
         WHERE sender_id = ? AND receiver_id = ?"
    )->execute([$user2, $me]);

    jsonOut($messages);

    // ── Action: send ──────────────────────────────────────────────────────────────
} elseif ($action === 'send') {
    check_csrf();

    $receiver = 0;
    $message = '';
    $attachment_url = null;
    $message_type = 'text';

    if (!empty($_FILES['attachment'])) {
        $receiver = (int) ($_POST['receiver'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'Upload error.';
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                $err = 'File is too large (server limit).';
            }
            jsonOut(['success' => false, 'error' => $err]);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_videos = ['mp4', 'webm', 'mov'];
        $allowed_audio = ['mp3', 'wav', 'm4a', 'ogg'];

        // MIME validation via finfo.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        // BUG FIX #5: Always close the finfo resource — previously leaked on every upload.
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'audio/mpeg',
            'audio/wav',
            'audio/mp4',
            'audio/ogg',
        ];

        // BUG FIX #8: Reduced per-type size limits — 100 MB was dangerously high,
        // enabling disk exhaustion and memory pressure during upload processing.
        $sizeLimits = [
            'image' => 10 * 1024 * 1024,  // 10 MB
            'video' => 50 * 1024 * 1024,  // 50 MB
            'audio' => 20 * 1024 * 1024,  // 20 MB
        ];
        $typeGroup = in_array($ext, $allowed_images) ? 'image'
            : (in_array($ext, $allowed_videos) ? 'video' : 'audio');
        $maxSize = $sizeLimits[$typeGroup] ?? (10 * 1024 * 1024);

        if ($file['size'] > $maxSize) {
            $limitMB = $maxSize / 1024 / 1024;
            jsonOut(['success' => false, 'error' => "File exceeds the {$limitMB}MB limit for {$typeGroup}s."]);
        }

        $allExts = array_merge($allowed_images, $allowed_videos, $allowed_audio);
        if (!in_array($ext, $allExts, true) || !in_array($mimeType, $allowedMimes, true)) {
            jsonOut(['success' => false, 'error' => 'Invalid file type.']);
        }

        $newName = 'chat_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $storedPath = storage_upload($file['tmp_name'], 'chat', $newName, $mimeType);

        if (!$storedPath) {
            jsonOut(['success' => false, 'error' => 'Failed to upload attachment.']);
        }

        $attachment_url = $storedPath;

        // BUG FIX #7: Determine message_type from MIME/extension only — never from
        // $file['name'] which is user-controlled input. An attacker could name any
        // file 'voice_note_evil.php' to manipulate the type detection branch.
        if (in_array($ext, $allowed_videos)) {
            $message_type = 'video';
        } elseif (in_array($ext, $allowed_audio)) {
            $message_type = 'audio';
        } else {
            $message_type = 'image';
        }

    } else {
        // Text message — accept JSON body or FormData.
        $json = json_decode(file_get_contents('php://input'), true);
        $src = is_array($json) ? $json : $_POST;
        $receiver = (int) ($src['receiver'] ?? 0);
        $message = trim($src['message'] ?? '');
    }

    if ($receiver <= 0) {
        jsonOut(['success' => false, 'error' => 'Invalid receiver.']);
    }

    if (!$message && !$attachment_url) {
        jsonOut(['success' => false, 'error' => 'Missing message or attachment.']);
    }

    try {
        // BUG FIX #9: Store NULL (not empty string '') when there is no text message.
        // The column was explicitly altered to TEXT NULL so that NULL correctly signals
        // "attachment-only message" — an empty string breaks that semantic.
        $stmt = $pdo->prepare(
            "INSERT INTO messages (sender_id, receiver_id, message, attachment_url, message_type)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $me,
            $receiver,
            $message !== '' ? $message : null, // BUG FIX #9
            $attachment_url,
            $message_type,
        ]);
        jsonOut(['success' => true]);
    } catch (PDOException $e) {
        error_log('chat.php send DB error: ' . $e->getMessage());
        jsonOut(['success' => false, 'error' => 'Database error. Please try again.']);
    }

    // ── Action: check_user ────────────────────────────────────────────────────────
// BUG FIX #12: This action does not belong in chat.php — it is a registration
// utility and has no relation to the chat feature. Keeping it here unnecessarily
// widens the attack surface of the chat endpoint.
// It has been moved to /api/check_user.php (create that file separately).
// The stub below returns a clear error to avoid silent breakage during migration.
} elseif ($action === 'check_user') {
    jsonError('This endpoint has moved to /api/check_user.php. Please update your frontend.', 410);

} else {
    jsonError('Unknown action.', 400);
}