<?php
$page_title = 'Omni Chat';
require_once 'header.php';

// Add the admin_deleted column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN admin_deleted TINYINT(1) DEFAULT 0");
} catch(PDOException $e) { /* already exists */ }

if (isset($_GET['delete_chat'])) {
    // Soft delete a specific conversation thread from admin view
    $u1 = (int)$_GET['u1'];
    $u2 = (int)$_GET['u2'];
    $pdo->prepare("UPDATE messages SET admin_deleted = 1 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)")->execute([$u1, $u2, $u2, $u1]);
    auditLog($pdo, $_SESSION['user_id'], "Admin cleared chat view between User #$u1 and User #$u2");
    header("Location: messages.php"); exit;
}

$view = isset($_GET['view']) ? $_GET['view'] : 'list';

if ($view === 'chat') {
    $u1 = (int)$_GET['u1'];
    $u2 = (int)$_GET['u2'];
    $msgs = $pdo->prepare("SELECT m.*, s.username as sender_name, r.username as receiver_name 
        FROM messages m 
        JOIN users s ON m.sender_id = s.id 
        JOIN users r ON m.receiver_id = r.id 
        WHERE m.admin_deleted = 0 AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)) 
        ORDER BY m.created_at ASC");
    $msgs->execute([$u1, $u2, $u2, $u1]);
    $chatLogs = $msgs->fetchAll();
} else {
    // Group conversations by unique pairs
    $conversations = $pdo->query("
        SELECT 
            LEAST(m.sender_id, m.receiver_id) as u1,
            GREATEST(m.sender_id, m.receiver_id) as u2,
            MAX(m.created_at) as last_msg_time,
            COUNT(*) as total_msgs
        FROM messages m
        WHERE m.admin_deleted = 0
        GROUP BY u1, u2
        ORDER BY last_msg_time DESC
        LIMIT 100
    ")->fetchAll();
    
    foreach ($conversations as &$c) {
        $u1_user = getUser($pdo, $c['u1']) ?? ['username' => 'System/Unknown'];
        $u2_user = getUser($pdo, $c['u2']) ?? ['username' => 'System/Unknown'];
        $c['u1_name'] = $u1_user['username'];
        $c['u2_name'] = $u2_user['username'];
    }
}
?>

<div class="flex-between mb-3">
    <h2 class="mb-1">💬 Omni Chat Monitor</h2>
    <?php if($view === 'chat'): ?>
        <a href="messages.php" class="btn btn-outline btn-sm">← Back to List</a>
    <?php endif; ?>
</div>

<?php if($view === 'list'): ?>
<div class="glass fade-in" style="padding:1.5rem; overflow-y:auto;">
    <?php if(count($conversations) > 0): ?>
        <table style="width:100%;">
            <thead><tr><th>Participants</th><th>Messages</th><th>Last Activity</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($conversations as $c): ?>
                <tr>
                    <td>
                        <strong style="color:var(--primary);"><?= htmlspecialchars($c['u1_name']) ?></strong> 
                        <span style="color:var(--text-muted); font-size:0.8rem;"> & </span> 
                        <strong style="color:var(--primary);"><?= htmlspecialchars($c['u2_name']) ?></strong>
                    </td>
                    <td><?= $c['total_msgs'] ?></td>
                    <td style="font-size:0.85rem;"><?= date('M d, H:i', strtotime($c['last_msg_time'])) ?></td>
                    <td>
                        <a href="?view=chat&u1=<?= $c['u1'] ?>&u2=<?= $c['u2'] ?>" class="btn btn-primary btn-sm">View Chat</a>
                        <a href="?delete_chat=1&u1=<?= $c['u1'] ?>&u2=<?= $c['u2'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this conversation from admin view? Users will still see it on their accounts.')">Hide</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted text-center">No active conversations found.</p>
    <?php endif; ?>
</div>
<?php elseif($view === 'chat'): ?>
<div class="glass fade-in" style="padding:1.5rem;">
    <h4 class="mb-3">Conversation Transcript</h4>
    <div style="background:rgba(0,0,0,0.02); border-radius:12px; padding:1.5rem; max-height:500px; overflow-y:auto; border:1px solid var(--border);">
        <?php foreach($chatLogs as $msg): ?>
            <div style="margin-bottom:1rem;">
                <span style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, H:i', strtotime($msg['created_at'])) ?></span><br>
                <strong style="color: <?= $msg['sender_id'] == $u1 ? 'var(--mint)' : 'var(--gold)' ?>;"><?= htmlspecialchars($msg['sender_name']) ?>:</strong> 
                <span style="font-size:0.95rem; line-height:1.5;"><?= htmlspecialchars($msg['message']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
