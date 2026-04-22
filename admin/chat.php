<?php
$page_title = 'Omni Chat Dashboard';
require_once 'header.php';

// PostgreSQL-compatible query. Uses STRING_AGG (replaces MySQL's GROUP_CONCAT)
// and SPLIT_PART (replaces MySQL's SUBSTRING_INDEX) to extract the latest message.
// No SET SESSION needed — STRING_AGG has no length cap.
$query = "SELECT 
            LEAST(m.sender_id, m.receiver_id) as u1, 
            GREATEST(m.sender_id, m.receiver_id) as u2, 
            MAX(m.created_at) as last_msg,
            COUNT(*) as total_msgs,
            SPLIT_PART(
                STRING_AGG(m.message, '|||' ORDER BY m.created_at DESC),
                '|||', 1
            ) as latest_text,
            usr1.username as u1_name,
            usr2.username as u2_name
          FROM messages m 
          JOIN users usr1 ON LEAST(m.sender_id, m.receiver_id) = usr1.id
          JOIN users usr2 ON GREATEST(m.sender_id, m.receiver_id) = usr2.id
          GROUP BY u1, u2, u1_name, u2_name 
          ORDER BY last_msg DESC";
$convos = $pdo->query($query)->fetchAll();

// FIX #1b: Read GET params once into explicit variables. Using these in the
// redirect after POST is now safe and deliberate, not accidental.
$selected_u1 = isset($_GET['u1']) ? (int) $_GET['u1'] : null;
$selected_u2 = isset($_GET['u2']) ? (int) $_GET['u2'] : null;

$transcript = [];
$selected_u1_name = 'Unknown User';
$selected_u2_name = 'Unknown User';

if ($selected_u1 && $selected_u2) {
    // FIX #2 (original): Explicitly fetch the names for the header.
    $stmt_users = $pdo->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
    $stmt_users->execute([$selected_u1, $selected_u2]);
    $user_names = $stmt_users->fetchAll(PDO::FETCH_KEY_PAIR);

    $selected_u1_name = $user_names[$selected_u1] ?? 'Unknown User';
    $selected_u2_name = $user_names[$selected_u2] ?? 'Unknown User';

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

    // FIX #2: Validate that to_id is one of the two users currently in scope.
    // This prevents an attacker from manipulating the POST body to send
    // admin-tagged messages to arbitrary user IDs.
    $to_id = (int) $_POST['to_id'];
    $valid_recipients = [$selected_u1, $selected_u2];
    if (!in_array($to_id, $valid_recipients, true)) {
        http_response_code(403);
        die("Invalid recipient.");
    }

    if ($reply && $to_id) {
        // FIX #9: Use a dedicated DB flag (is_support_message = 1) instead of
        // a plain-text prefix so regular users cannot spoof the purple bubble
        // by typing "[Admin Support]" themselves. The schema should have:
        //   ALTER TABLE messages ADD COLUMN is_support_message TINYINT(1) NOT NULL DEFAULT 0;
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_support_message) VALUES (?,?,?,TRUE)")
            ->execute([$_SESSION['user_id'], $to_id, $reply]);

        // FIX #3 (original) + FIX #1b: Redirect uses the explicitly-read GET
        // variables, not silently relying on scope behaviour during POST.
        header("Location: ?u1={$selected_u1}&u2={$selected_u2}");
        exit;
    }
}
?>

<div class="chat-layout">

    <div class="glass" style="padding:1.5rem; display:flex; flex-direction:column; overflow:hidden;">
        <h3 class="mb-3" style="display:flex; align-items:center; gap:0.5rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            Active Conversations
        </h3>
        <div style="flex:1; overflow-y:auto; padding-right:5px;">
            <?php if (count($convos) > 0): ?>
                <?php foreach ($convos as $c):
                    $isActive = ($selected_u1 == $c['u1'] && $selected_u2 == $c['u2']);
                    ?>
                    <a href="?u1=<?= $c['u1'] ?>&u2=<?= $c['u2'] ?>"
                        style="display:block; padding:1rem; border-radius:12px; border:1px solid <?= $isActive ? 'var(--primary)' : 'rgba(255,255,255,0.05)' ?>; background:<?= $isActive ? 'rgba(0,113,227,0.1)' : 'rgba(255,255,255,0.02)' ?>; margin-bottom:0.75rem; text-decoration:none; transition:all 0.2s;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <strong style="color:<?= $isActive ? 'var(--primary)' : '#fff' ?>; font-size:0.9rem;">
                                <?= htmlspecialchars($c['u1_name']) ?> ⬌ <?= htmlspecialchars($c['u2_name']) ?>
                            </strong>
                            <span
                                style="font-size:0.7rem; color:var(--text-muted);"><?= date('M d, H:i', strtotime($c['last_msg'])) ?></span>
                        </div>
                        <p class="text-muted"
                            style="font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($c['latest_text']) ?></p>
                        <div style="font-size:0.7rem; color:var(--primary); font-weight:700; margin-top:4px;">
                            <?= $c['total_msgs'] ?> messages total</div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:2rem;">No messages found in the system.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="glass" style="padding:1.5rem; display:flex; flex-direction:column; overflow:hidden;">
        <?php if ($selected_u1 && $selected_u2): ?>
            <div
                style="border-bottom:1px solid var(--border); padding-bottom:1rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0;">Conversation Transcript</h3>
                    <p class="text-muted" style="font-size:0.85rem;">Inspecting chat between
                        <strong><?= htmlspecialchars($selected_u1_name) ?></strong> and
                        <strong><?= htmlspecialchars($selected_u2_name) ?></strong></p>
                </div>
                <button class="btn btn-outline btn-sm" onclick="location.reload();">Refresh Feed</button>
            </div>

            <div id="transcript-scroll"
                style="flex:1; overflow-y:auto; padding:1rem; background:rgba(0,0,0,0.2); border-radius:12px; margin-bottom:1.5rem; display:flex; flex-direction:column; gap:1rem;">
                <?php if (count($transcript) > 0): ?>
                    <?php foreach ($transcript as $m):
                        // FIX #9: Detect support messages via the DB flag, not a plain-text prefix.
                        // PostgreSQL returns 't'/'f' strings for booleans via PDO,
                        // so compare explicitly rather than using !empty().
                        $isSupport = ($m['is_support_message'] === true || $m['is_support_message'] === 't' || $m['is_support_message'] === '1');

                        // Align User 1 to the left. Align User 2 and Admin Support to the right.
                        $alignRight = $isSupport || ($m['sender_id'] == $selected_u2);

                        // Give User 2 a slightly different bubble color so the two users are distinct.
                        $bubbleColor = 'rgba(255,255,255,0.05)'; // Default: User 1 (Gray)
                        if ($m['sender_id'] == $selected_u2) {
                            $bubbleColor = 'rgba(0,113,227,0.1)'; // User 2 (Slightly Blue)
                        }
                        if ($isSupport) {
                            $bubbleColor = 'linear-gradient(135deg, #af52de, #7d3ca1)'; // Admin (Purple)
                        }
                        ?>
                        <div style="max-width:80%; align-self:<?= $alignRight ? 'flex-end' : 'flex-start' ?>;">
                            <div
                                style="font-size:0.7rem; color:var(--text-muted); margin-bottom:3px; text-align:<?= $alignRight ? 'right' : 'left' ?>;">
                                <?= htmlspecialchars($m['sender_name']) ?> &bull; <?= date('H:i', strtotime($m['created_at'])) ?>
                            </div>
                            <div
                                style="padding:0.75rem 1rem; border-radius:16px; background:<?= $bubbleColor ?>; color:#fff; font-size:0.9rem; border:1px solid rgba(255,255,255,0.1);">
                                <?php if ($isSupport): ?>
                                    <span
                                        style="font-size:0.7rem; font-weight:700; opacity:0.75; display:block; margin-bottom:4px; letter-spacing:0.05em;">ADMIN
                                        SUPPORT</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($m['message']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- FIX #8: Show a placeholder when the transcript is empty -->
                    <div
                        style="flex:1; display:flex; align-items:center; justify-content:center; opacity:0.4; font-size:0.9rem;">
                        No messages in this conversation yet.
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" style="display:flex; gap:0.75rem; align-items:flex-end;">
                <?= csrf_field() ?>
                <div style="flex:1;">
                    <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:8px;">Send a
                        message as Support:</label>
                    <select name="to_id" class="form-control" style="margin-bottom:8px; font-size:0.85rem; padding:0.4rem;">
                        <option value="<?= $selected_u1 ?>">Reply to <?= htmlspecialchars($selected_u1_name) ?></option>
                        <option value="<?= $selected_u2 ?>">Reply to <?= htmlspecialchars($selected_u2_name) ?></option>
                    </select>
                    <textarea name="message" class="form-control" placeholder="Type support message..." required
                        style="min-height:80px;"></textarea>
                </div>
                <button type="submit" name="admin_reply" class="btn btn-primary" style="padding:1rem 2rem;">Send
                    Reply</button>
            </form>

            <script>
                const scroll = document.getElementById('transcript-scroll');
                scroll.scrollTop = scroll.scrollHeight;
            </script>
        <?php else: ?>
            <div
                style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
                <div style="font-size:3rem; margin-bottom:1rem; opacity:0.3;">💬</div>
                <h3>Omni Chat Panel</h3>
                <p class="text-muted" style="max-width:300px;">Select a conversation from the sidebar to inspect the
                    transcript or intervene as administrator.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .chat-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 1.5rem;
        height: calc(100vh - 150px);
    }

    @media(max-width: 900px) {
        .chat-layout {
            grid-template-columns: 1fr;
            height: auto;
        }

        .chat-layout>.glass:first-child {
            height: 400px;
        }
    }
</style>

<?php require_once 'footer.php'; ?>