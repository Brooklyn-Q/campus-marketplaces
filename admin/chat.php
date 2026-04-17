<?php
$page_title = 'Omni Chat Dashboard';
require_once 'header.php';

// Fetch all distinct conversations (user pairs)
$query = "SELECT 
            LEAST(sender_id, receiver_id) as u1, 
            GREATEST(sender_id, receiver_id) as u2, 
            MAX(created_at) as last_msg,
            COUNT(*) as total_msgs,
            SUBSTRING_INDEX(GROUP_CONCAT(message ORDER BY created_at DESC SEPARATOR '|||'), '|||', 1) as latest_text
          FROM messages 
          GROUP BY u1, u2 
          ORDER BY last_msg DESC";
$convos = $pdo->query($query)->fetchAll();

$selected_u1 = isset($_GET['u1']) ? (int)$_GET['u1'] : null;
$selected_u2 = isset($_GET['u2']) ? (int)$_GET['u2'] : null;

$transcript = [];
if ($selected_u1 && $selected_u2) {
    $st = $pdo->prepare("SELECT m.*, s.username as sender_name, r.username as receiver_name 
                         FROM messages m 
                         JOIN users s ON m.sender_id = s.id 
                         JOIN users r ON m.receiver_id = r.id 
                         WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                            OR (m.sender_id = ? AND m.receiver_id = ?) 
                         ORDER BY m.created_at ASC");
    $st->execute([$selected_u1, $selected_u2, $selected_u2, $selected_u1]);
    $transcript = $st->fetchAll();
}

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    check_csrf();
    $reply = trim($_POST['message'] ?? '');
    $to_id = (int)$_POST['to_id']; // Which user to reply to specifically? or both?
    // Usually Omni chat means joining the thread. 
    // We'll reply to the "other" user (not admin).
    if ($reply && $to_id) {
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)")
            ->execute([$_SESSION['user_id'], $to_id, "[Admin Support] " . $reply]);
        header("Location: chat.php?u1=$selected_u1&u2=$selected_u2");
        exit;
    }
}
?>

<div style="display:grid; grid-template-columns:350px 1fr; gap:1.5rem; height:calc(100vh - 150px);">
    
    <!-- Conversation List -->
    <div class="glass" style="padding:1.5rem; display:flex; flex-direction:column; overflow:hidden;">
        <h3 class="mb-3" style="display:flex; align-items:center; gap:0.5rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Active Conversations
        </h3>
        <div style="flex:1; overflow-y:auto; padding-right:5px;">
            <?php if(count($convos) > 0): ?>
                <?php foreach($convos as $c): 
                    $u1_info = getUser($pdo, $c['u1']);
                    $u2_info = getUser($pdo, $c['u2']);
                    $isActive = ($selected_u1 == $c['u1'] && $selected_u2 == $c['u2']);
                ?>
                    <a href="?u1=<?= $c['u1'] ?>&u2=<?= $c['u2'] ?>" 
                       style="display:block; padding:1rem; border-radius:12px; border:1px solid <?= $isActive ? 'var(--primary)' : 'rgba(255,255,255,0.05)' ?>; background:<?= $isActive ? 'rgba(0,113,227,0.1)' : 'rgba(255,255,255,0.02)' ?>; margin-bottom:0.75rem; text-decoration:none; transition:all 0.2s;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <strong style="color:<?= $isActive ? 'var(--primary)' : '#fff' ?>; font-size:0.9rem;">
                                <?= htmlspecialchars($u1_info['username']) ?> ⬌ <?= htmlspecialchars($u2_info['username']) ?>
                            </strong>
                            <span style="font-size:0.7rem; color:var(--text-muted);"><?= date('M d, H:i', strtotime($c['last_msg'])) ?></span>
                        </div>
                        <p class="text-muted" style="font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($c['latest_text']) ?></p>
                        <div style="font-size:0.7rem; color:var(--primary); font-weight:700; margin-top:4px;"><?= $c['total_msgs'] ?> messages total</div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:2rem;">No messages found in the system.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transcript Area -->
    <div class="glass" style="padding:1.5rem; display:flex; flex-direction:column; overflow:hidden;">
        <?php if($selected_u1 && $selected_u2): ?>
            <div style="border-bottom:1px solid var(--border); padding-bottom:1rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0;">Conversation Transcript</h3>
                    <p class="text-muted" style="font-size:0.85rem;">Inspecting chat between <strong><?= htmlspecialchars($u1_info['username']) ?></strong> and <strong><?= htmlspecialchars($u2_info['username']) ?></strong></p>
                </div>
                <!-- Admin Action -->
                <button class="btn btn-outline btn-sm" onclick="location.reload();">Refresh Feed</button>
            </div>

            <div id="transcript-scroll" style="flex:1; overflow-y:auto; padding:1rem; background:rgba(0,0,0,0.2); border-radius:12px; margin-bottom:1.5rem; display:flex; flex-direction:column; gap:1rem;">
                <?php foreach($transcript as $m): 
                    $isSupport = (strpos($m['message'], '[Admin Support]') === 0);
                    $isAdminSent = ($m['sender_id'] == $_SESSION['user_id']);
                ?>
                    <div style="max-width:80%; align-self:<?= $isAdminSent ? 'flex-end' : 'flex-start' ?>;">
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:3px; text-align:<?= $isAdminSent ? 'right' : 'left' ?>;">
                            <?= htmlspecialchars($m['sender_name']) ?> &bull; <?= date('H:i', strtotime($m['created_at'])) ?>
                        </div>
                        <div style="padding:0.75rem 1rem; border-radius:16px; background:<?= $isSupport ? 'linear-gradient(135deg, #af52de, #7d3ca1)' : ($isAdminSent ? 'var(--primary)' : 'rgba(255,255,255,0.05)') ?>; color:#fff; font-size:0.9rem; border:1px solid rgba(255,255,255,0.1);">
                            <?= htmlspecialchars($m['message']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Omni Reply Form -->
            <form method="POST" style="display:flex; gap:0.75rem; align-items:flex-end;">
                <?= csrf_field() ?>
                <div style="flex:1;">
                    <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:8px;">Send a message as Support:</label>
                    <select name="to_id" class="form-control" style="margin-bottom:8px; font-size:0.85rem; padding:0.4rem;">
                        <option value="<?= $selected_u1 ?>">Reply to <?= htmlspecialchars($u1_info['username']) ?></option>
                        <option value="<?= $selected_u2 ?>">Reply to <?= htmlspecialchars($u2_info['username']) ?></option>
                    </select>
                    <textarea name="message" class="form-control" placeholder="Type support message..." required style="min-height:80px;"></textarea>
                </div>
                <button type="submit" name="admin_reply" class="btn btn-primary" style="padding:1rem 2rem;">Send Reply</button>
            </form>

            <script>
                const scroll = document.getElementById('transcript-scroll');
                scroll.scrollTop = scroll.scrollHeight;
            </script>
        <?php else: ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
                <div style="font-size:3rem; margin-bottom:1rem; opacity:0.3;">💬</div>
                <h3>Omni Chat Panel</h3>
                <p class="text-muted" style="max-width:300px;">Select a conversation from the sidebar to inspect the transcript or intervene as administrator.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'footer.php'; ?>
