<?php
$page_title = 'Audit Log';
require_once 'header.php';

$msg = '';

// 1. FIXED UX: Process the action, set a session message, and redirect (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    check_csrf();
    $pdo->exec("TRUNCATE TABLE audit_log");
    
    // Log the clear action (this will become ID #1 in the fresh table)
    auditLog($pdo, $_SESSION['user_id'], "Cleared all audit logs");
    
    $_SESSION['flash_msg'] = "✅ Audit logs cleared successfully.";
    header("Location: audit_log.php");
    exit;
}

// Check for flash message from redirect
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// 2. FIXED DATA LOSS: Changed to LEFT JOIN so logs from deleted admins still show up
$logs = $pdo->query("SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.admin_id = u.id ORDER BY a.created_at DESC LIMIT 100")->fetchAll();
?>

<div class="flex-between mb-3">
    <div>
        <h2 class="mb-1">🔍 Audit Log</h2>
        <p class="text-muted">Complete history of all admin actions.</p>
    </div>
    <form method="POST" onsubmit="return confirm('Are you sure you want to clear ALL audit logs?');">
        <?= csrf_field() ?>
        <button type="submit" name="clear_logs" class="btn btn-danger btn-sm">🗑️ Clear All Logs</button>
    </form>
</div>

<?php if($msg): ?>
    <div class="alert alert-success mb-3 fade-in"><?= $msg ?></div>
<?php endif; ?>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto;">
    <table>
        <thead><tr><th>ID</th><th>Admin</th><th>Action</th><th>Target</th><th>When</th></tr></thead>
        <tbody>
            <?php foreach($logs as $l): ?>
            <tr>
                <td><?= $l['id'] ?></td>
                <td><strong><?= htmlspecialchars($l['username'] ?? 'System/Deleted Admin') ?></strong></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= $l['target_type'] ? htmlspecialchars($l['target_type']) . ' #' . $l['target_id'] : '—' ?></td>
                <td style="font-size:0.85rem;"><?= date('M d, Y H:i', strtotime($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($logs) === 0): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding: 2rem;">No admin actions logged yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>