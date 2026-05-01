<?php
$page_title = 'Omni Chat';

// Load DB + session BEFORE header.php to handle POST redirects
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$adminAccess = ensureAdminPageAccess($pdo);

$currentAdminId = (int) ($_SESSION['user_id'] ?? 0);
$selected_u1 = (int) ($_GET['u1'] ?? $_POST['u1'] ?? 0);
$selected_u2 = (int) ($_GET['u2'] ?? $_POST['u2'] ?? 0);

// Run migration once
static $migrated = false;
if (!$migrated) {
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS admin_deleted BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_support_message BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {
    }
    $migrated = true;
}

// Handle POST-based hide action (BEFORE any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_chat'])) {
    check_csrf();

    $u1 = (int) $_POST['u1'];
    $u2 = (int) $_POST['u2'];
    $stmt = $pdo->prepare(
        "UPDATE messages SET admin_deleted = TRUE
         WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)"
    );
    $stmt->execute([$u1, $u2, $u2, $u1]);
    auditLog($pdo, $_SESSION['user_id'], "Admin cleared chat view between User #$u1 and User #$u2");

    $_SESSION['flash_msg'] = "Conversation between User #$u1 and User #$u2 has been hidden.";
    header("Location: messages.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    check_csrf();

    $reply = trim((string) ($_POST['message'] ?? ''));
    $toId = (int) ($_POST['to_id'] ?? 0);
    $conversationIds = array_values(array_filter([$selected_u1, $selected_u2]));
    $canReplyInThread = in_array($currentAdminId, $conversationIds, true);
    $validRecipients = array_values(array_filter($conversationIds, static fn($id) => $id !== $currentAdminId));

    if (!$canReplyInThread || !in_array($toId, $validRecipients, true)) {
        http_response_code(403);
        exit('Invalid reply target.');
    }

    if ($reply !== '') {
        $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, is_support_message)
            VALUES (?, ?, ?, TRUE)
        ")->execute([$currentAdminId, $toId, $reply]);
        createMessageNotification($pdo, $toId, $currentAdminId, $reply);

        $_SESSION['flash_msg'] = 'Reply sent successfully.';
        header("Location: messages.php?view=chat&u1={$selected_u1}&u2={$selected_u2}");
        exit;
    }
}

require_once 'header.php';

// Whitelist the view parameter
$allowed_views = ['list', 'chat'];
$view = in_array($_GET['view'] ?? '', $allowed_views) ? $_GET['view'] : 'list';

if ($view === 'chat') {
    $u1 = $selected_u1;
    $u2 = $selected_u2;
    $participants = [];
    $replyTargets = [];
    $canReplyHere = false;

    $msgs = $pdo->prepare(
        "SELECT m.*, s.username AS sender_name, r.username AS receiver_name
         FROM messages m
         JOIN users s ON m.sender_id  = s.id
         JOIN users r ON m.receiver_id = r.id
         WHERE m.admin_deleted = FALSE
           AND ((m.sender_id = ? AND m.receiver_id = ?)
             OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.created_at ASC"
    );
    $msgs->execute([$u1, $u2, $u2, $u1]);
    $chatLogs = $msgs->fetchAll();

    if ($u1 > 0 && $u2 > 0) {
        $usersStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
        $usersStmt->execute([$u1, $u2]);
        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participants[(int) $row['id']] = $row['username'];
        }

        $canReplyHere = in_array($currentAdminId, [$u1, $u2], true);
        if ($canReplyHere) {
            foreach ($participants as $participantId => $participantName) {
                if ($participantId !== $currentAdminId) {
                    $replyTargets[$participantId] = $participantName;
                }
            }
        }
    }

} else {
    $conversations = $pdo->query("
        SELECT
            LEAST(m.sender_id,    m.receiver_id) AS u1,
            GREATEST(m.sender_id, m.receiver_id) AS u2,
            MAX(m.created_at)                    AS last_msg_time,
            COUNT(*)                             AS total_msgs,
            u1_t.username                        AS u1_name,
            u2_t.username                        AS u2_name
        FROM messages m
        JOIN users u1_t ON u1_t.id = LEAST(m.sender_id,    m.receiver_id)
        JOIN users u2_t ON u2_t.id = GREATEST(m.sender_id, m.receiver_id)
        WHERE m.admin_deleted = FALSE
        GROUP BY u1, u2, u1_name, u2_name
        ORDER BY last_msg_time DESC
        LIMIT 100
    ")->fetchAll();
}

$csrf_token = $_SESSION['csrf_token'];
?>

<div class="flex-between mb-3">
    <h2 class="mb-1">💬 Omni Chat Monitor</h2>
    <?php if ($view === 'chat'): ?>
        <a href="messages.php" class="btn btn-outline btn-sm">← Back to List</a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['flash_msg'])): ?>
    <div class="alert alert-success mb-3">
        <?= htmlspecialchars($_SESSION['flash_msg']) ?>
    </div>
    <?php unset($_SESSION['flash_msg']); ?>
<?php endif; ?>

<?php if ($view === 'list'): ?>
    <div class="glass fade-in" style="padding:1.5rem; overflow-y:auto;">
        <?php if (count($conversations) > 0): ?>
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Participants</th>
                        <th>Messages</th>
                        <th>Last Activity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $c): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--primary);"><?= htmlspecialchars($c['u1_name']) ?></strong>
                                <span style="color:var(--text-muted); font-size:0.8rem;"> &amp; </span>
                                <strong style="color:var(--primary);"><?= htmlspecialchars($c['u2_name']) ?></strong>
                            </td>
                            <td><?= (int) $c['total_msgs'] ?></td>
                            <td style="font-size:0.85rem;"><?= htmlspecialchars(date('M d, H:i', strtotime($c['last_msg_time']))) ?>
                            </td>
                            <td>
                                <a href="?view=chat&u1=<?= (int) $c['u1'] ?>&u2=<?= (int) $c['u2'] ?>"
                                    class="btn btn-primary btn-sm">View Chat</a>
                                <form method="POST" action="messages.php" style="display:inline;"
                                    onsubmit="return confirm('Hide this conversation from admin view? Users will still see it.')">
                                    <input type="hidden" name="delete_chat" value="1">
                                    <input type="hidden" name="u1" value="<?= (int) $c['u1'] ?>">
                                    <input type="hidden" name="u2" value="<?= (int) $c['u2'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hide</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center">No active conversations found.</p>
        <?php endif; ?>
    </div>

<?php elseif ($view === 'chat'): ?>
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-3">Conversation Transcript</h4>
        <div
            style="background:rgba(0,0,0,0.02); border-radius:12px; padding:1.5rem; max-height:500px; overflow-y:auto; border:1px solid var(--border);">
            <?php foreach ($chatLogs as $msg): ?>
                <?php $isSupport = ($msg['is_support_message'] === true || $msg['is_support_message'] === 't' || $msg['is_support_message'] === '1'); ?>
                <div style="margin-bottom:1rem;">
                    <span style="font-size:0.8rem; color:var(--text-muted);">
                        <?= htmlspecialchars(date('M d, H:i', strtotime($msg['created_at']))) ?>
                    </span><br>
                    <strong style="color: <?= $msg['sender_id'] == $u1 ? 'var(--mint)' : 'var(--gold)' ?>;">
                        <?= htmlspecialchars($msg['sender_name']) ?>:
                    </strong>
                    <?php if ($isSupport): ?>
                        <span style="font-size:0.7rem; font-weight:700; color:var(--primary); margin-left:0.35rem;">ADMIN SUPPORT</span>
                    <?php endif; ?>
                    <span style="font-size:0.95rem; line-height:1.5;">
                        <?= htmlspecialchars($msg['message']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canReplyHere && count($replyTargets) > 0): ?>
            <form method="POST" action="messages.php?view=chat&u1=<?= (int) $u1 ?>&u2=<?= (int) $u2 ?>" style="margin-top:1rem; display:flex; gap:0.75rem; align-items:flex-end;">
                <?= csrf_field() ?>
                <input type="hidden" name="u1" value="<?= (int) $u1 ?>">
                <input type="hidden" name="u2" value="<?= (int) $u2 ?>">
                <div style="flex:1;">
                    <label style="font-size:0.78rem; color:var(--text-muted); display:block; margin-bottom:0.45rem;">Reply as admin</label>
                    <select name="to_id" class="form-control" style="margin-bottom:0.6rem;">
                        <?php foreach ($replyTargets as $targetId => $targetName): ?>
                            <option value="<?= (int) $targetId ?>">Send to <?= htmlspecialchars($targetName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="message" class="form-control" placeholder="Type your reply..." required style="min-height:88px;"></textarea>
                </div>
                <button type="submit" name="admin_reply" class="btn btn-primary">Send Reply</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning" style="margin-top:1rem;">
                Reply is available only for conversations that include your admin account.
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
