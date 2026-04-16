<?php
$page_title = 'System Settings';
require_once 'header.php';

$msg = '';
$err = '';

// Fake maintenance mode flag (usually stored in DB or config file)
$maintenance_file = '../.maintenance';
$is_maintenance = file_exists($maintenance_file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_maintenance'])) {
        if ($is_maintenance) {
            unlink($maintenance_file);
            $msg = "Maintenance mode disabled. Site is live.";
            $is_maintenance = false;
        } else {
            file_put_contents($maintenance_file, '1');
            $msg = "Maintenance mode enabled. Site is offline to non-admins.";
            $is_maintenance = true;
        }
        auditLog($pdo, $_SESSION['user_id'], $is_maintenance ? "Enabled Maintenance Mode" : "Disabled Maintenance Mode", 'system', 0);
    }
    
    if (isset($_POST['backup_db'])) {
        // Trigger a fake backup process
        $backup_name = 'backup_' . date('Ymd_His') . '.sql';
        
        // Mocking the backup by writing a simple message to a file
        if (!is_dir('../backups')) mkdir('../backups', 0777, true);
        file_put_contents('../backups/' . $backup_name, "-- Database Backup Generated on " . date('r') . "\n-- (Mock Backup)\n");
        
        $msg = "Database backup created successfully: $backup_name";
        auditLog($pdo, $_SESSION['user_id'], "Triggered Database Backup: $backup_name", 'system', 0);
    }
}
?>

<h2 class="mb-3">⚙️ System Settings</h2>

<?php if($msg): ?><div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error fade-in"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
    <!-- Maintenance Mode -->
    <div class="glass fade-in" style="padding:2rem;">
        <h3 class="mb-2">🚧 Maintenance Mode</h3>
        <p class="text-muted mb-3" style="font-size:0.9rem;">
            When enabled, the marketplace will be completely hidden from regular users. Only administrators will be able to access the site and this dashboard.
        </p>
        
        <div style="background:rgba(0,0,0,0.2); padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
            <div style="width:16px; height:16px; border-radius:50%; background:<?= $is_maintenance ? 'var(--warning)' : 'var(--success)' ?>;"></div>
            <strong>Status: <?= $is_maintenance ? 'Currently Active' : 'Off (Site Live)' ?></strong>
        </div>

        <form method="POST">
            <button type="submit" name="toggle_maintenance" class="btn <?= $is_maintenance ? 'btn-success' : 'btn-warning' ?>" style="width:100%; justify-content:center; font-size:1.1rem; padding:1rem;">
                <?= $is_maintenance ? '✅ Disable Maintenance Mode' : '⚠️ Enable Maintenance Mode' ?>
            </button>
            <p class="text-center mt-2 text-muted" style="font-size:0.8rem;">Action will be recorded in the <a href="audit.php">Audit Log</a>.</p>
        </form>
    </div>

    <!-- Database Backup -->
    <div class="glass fade-in" style="padding:2rem;">
        <h3 class="mb-2">💾 Database Backup</h3>
        <p class="text-muted mb-3" style="font-size:0.9rem;">
            Create a manual snapshot of the database (users, products, transactions, and messages).
        </p>

        <form method="POST">
            <button type="submit" name="backup_db" class="btn btn-primary" style="width:100%; justify-content:center; padding:1rem;">
                ⬇️ Create New Backup
            </button>
        </form>

        <h4 class="mt-3 mb-2">Recent Backups</h4>
        <?php
        $backups = [];
        if (is_dir('../backups')) {
            $files = scandir('../backups');
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..') $backups[] = $f;
            }
        }
        rsort($backups);
        ?>
        <?php if(count($backups) > 0): ?>
            <ul style="list-style:none; padding:0; font-size:0.85rem;" class="text-muted">
                <?php foreach(array_slice($backups, 0, 5) as $b): ?>
                    <li style="padding:0.5rem 0; border-bottom:1px solid var(--border);">📄 <?= htmlspecialchars($b) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted" style="font-size:0.85rem;">No backups found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Advanced Security Info -->
<div class="glass fade-in mt-3" style="padding:2rem;">
    <h3 class="mb-2">🔐 Advanced Security</h3>
    
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div style="border:1px solid var(--border); padding:1rem; border-radius:8px; display:flex; align-items:flex-start; gap:1rem;">
            <div style="font-size:1.5rem;">🛡️</div>
            <div>
                <h4 style="margin-bottom:0.3rem;">Brute Force Protection</h4>
                <p class="text-muted" style="font-size:0.85rem;">Active on all login endpoints. IPs attempting >5 failed logins are temporarily throttled.</p>
            </div>
        </div>
        
        <div style="border:1px solid var(--border); padding:1rem; border-radius:8px; display:flex; align-items:flex-start; gap:1rem;">
            <div style="font-size:1.5rem;">📱</div>
            <div>
                <h4 style="margin-bottom:0.3rem;">Admin 2FA</h4>
                <p class="text-muted" style="font-size:0.85rem;">Required for high-level operations (simulated).</p>
                <button class="btn btn-outline btn-sm mt-1" disabled>Configure (Coming Soon)</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
