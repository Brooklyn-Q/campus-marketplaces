<?php
// Load DB + session BEFORE header.php so POST actions (check_csrf, redirects)
// can run before any HTML is output — same pattern as admin/index.php.
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$msg = '';
$err = '';

$maintenance_file = __DIR__ . '/../.maintenance';
$is_maintenance = file_exists($maintenance_file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (isset($_POST['toggle_maintenance'])) {
        if ($is_maintenance) {
            if (@unlink($maintenance_file)) {
                $is_maintenance = false;
                $msg = 'Maintenance mode disabled. Site is now live.';
                auditLog($pdo, $_SESSION['user_id'], 'Disabled Maintenance Mode', 'system', 0);
            } else {
                $err = 'Could not remove .maintenance file. Check server permissions.';
            }
        } else {
            if (@file_put_contents($maintenance_file, '1') !== false) {
                $is_maintenance = true;
                $msg = 'Maintenance mode enabled. Site is offline to non-admins.';
                auditLog($pdo, $_SESSION['user_id'], 'Enabled Maintenance Mode', 'system', 0);
            } else {
                $err = 'Could not create .maintenance file. Check server permissions.';
            }
        }
    }

    if (isset($_POST['backup_db'])) {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) @mkdir($backup_dir, 0750, true);
        $backup_name = 'backup_' . date('Ymd_His') . '.sql';
        $note = "-- Campus Marketplace DB Backup\n-- Generated: " . date('r') . "\n-- (snapshot placeholder — configure real pg_dump/mysqldump on the server)\n";
        if (@file_put_contents($backup_dir . '/' . $backup_name, $note) !== false) {
            $msg = "Backup record created: $backup_name";
            auditLog($pdo, $_SESSION['user_id'], "Triggered DB Backup: $backup_name", 'system', 0);
        } else {
            $err = 'Could not write backup file. Check server permissions.';
        }
    }
}

// Recent backups
$backups = [];
$backup_dir = __DIR__ . '/../backups';
if (is_dir($backup_dir)) {
    $files = array_filter(scandir($backup_dir), fn($f) => $f !== '.' && $f !== '..');
    rsort($files);
    $backups = array_slice(array_values($files), 0, 5);
}

// Now safe to load header — all logic that could die()/redirect() is done
$page_title = 'Settings';
require_once 'header.php';
?>

<h2 class="mb-3" style="display:flex; align-items:center; gap:0.5rem; color:var(--text-main); font-size:1.5rem; font-weight:800;">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
    </svg>
    System Settings
</h2>

<?php if ($msg): ?>
    <div style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); color:#4ade80; padding:0.85rem 1.1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#f87171; padding:0.85rem 1.1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">

    <!-- Maintenance Mode -->
    <div style="background:var(--bg-card,#1a1a2e); border:1px solid var(--border); border-radius:14px; padding:1.75rem;">
        <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-main); margin:0 0 0.5rem; display:flex; align-items:center; gap:0.5rem;">
            🚧 Maintenance Mode
        </h3>
        <p style="color:var(--text-muted); font-size:0.87rem; margin:0 0 1.25rem; line-height:1.5;">
            When enabled, the site is hidden from regular users. Admins can still browse freely.
        </p>

        <div style="display:flex; align-items:center; gap:0.75rem; background:rgba(0,0,0,0.2); padding:0.85rem 1rem; border-radius:9px; margin-bottom:1.25rem;">
            <div style="width:12px; height:12px; border-radius:50%; flex-shrink:0;
                background:<?= $is_maintenance ? '#f59e0b' : '#22c55e' ?>;
                box-shadow:0 0 6px <?= $is_maintenance ? '#f59e0b' : '#22c55e' ?>88;"></div>
            <span style="font-size:0.9rem; font-weight:600; color:var(--text-main);">
                <?= $is_maintenance ? 'Active — site is offline to users' : 'Off — site is live' ?>
            </span>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <button type="submit" name="toggle_maintenance"
                style="width:100%; padding:0.85rem; border:none; border-radius:9px; font-size:0.95rem; font-weight:700; cursor:pointer; transition:opacity 0.2s;
                    background:<?= $is_maintenance ? '#22c55e' : '#f59e0b' ?>;
                    color:<?= $is_maintenance ? '#fff' : '#000' ?>;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <?= $is_maintenance ? '✅ Disable — Bring Site Back Online' : '⚠️ Enable Maintenance Mode' ?>
            </button>
            <p style="text-align:center; margin:0.6rem 0 0; font-size:0.78rem; color:var(--text-muted);">
                Recorded in the <a href="audit.php" style="color:var(--primary);">Audit Log</a>.
            </p>
        </form>
    </div>

    <!-- Database Backup -->
    <div style="background:var(--bg-card,#1a1a2e); border:1px solid var(--border); border-radius:14px; padding:1.75rem;">
        <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-main); margin:0 0 0.5rem; display:flex; align-items:center; gap:0.5rem;">
            💾 Database Backup
        </h3>
        <p style="color:var(--text-muted); font-size:0.87rem; margin:0 0 1.25rem; line-height:1.5;">
            Create a manual backup record. Configure <code style="font-size:0.82rem; background:rgba(255,255,255,0.07); padding:1px 5px; border-radius:4px;">pg_dump</code> on the server for full exports.
        </p>

        <form method="POST" style="margin-bottom:1.25rem;">
            <?= csrf_field() ?>
            <button type="submit" name="backup_db"
                style="width:100%; padding:0.85rem; border:none; border-radius:9px; font-size:0.95rem; font-weight:700; cursor:pointer; background:var(--primary,#7c3aed); color:#fff; transition:opacity 0.2s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                ⬇️ Create Backup Record
            </button>
        </form>

        <h4 style="font-size:0.85rem; font-weight:700; color:var(--text-muted); margin:0 0 0.5rem; text-transform:uppercase; letter-spacing:0.05em;">Recent Backups</h4>
        <?php if ($backups): ?>
            <ul style="list-style:none; padding:0; margin:0;">
                <?php foreach ($backups as $b): ?>
                    <li style="padding:0.45rem 0; border-bottom:1px solid var(--border); font-size:0.83rem; color:var(--text-muted); display:flex; align-items:center; gap:0.5rem;">
                        📄 <?= htmlspecialchars($b) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">No backups yet.</p>
        <?php endif; ?>
    </div>

</div>

<!-- Security Overview -->
<div style="background:var(--bg-card,#1a1a2e); border:1px solid var(--border); border-radius:14px; padding:1.75rem; margin-bottom:1.5rem;">
    <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-main); margin:0 0 1.25rem; display:flex; align-items:center; gap:0.5rem;">
        🔐 Security Overview
    </h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:1rem;">
        <div style="border:1px solid var(--border); padding:1rem; border-radius:10px; display:flex; align-items:flex-start; gap:0.75rem;">
            <div style="font-size:1.4rem; flex-shrink:0;">🛡️</div>
            <div>
                <div style="font-weight:700; color:var(--text-main); font-size:0.9rem; margin-bottom:0.25rem;">Brute Force Protection</div>
                <div style="font-size:0.82rem; color:var(--text-muted); line-height:1.4;">Active on all login endpoints. IPs with &gt;5 failed attempts are throttled.</div>
            </div>
        </div>
        <div style="border:1px solid var(--border); padding:1rem; border-radius:10px; display:flex; align-items:flex-start; gap:0.75rem;">
            <div style="font-size:1.4rem; flex-shrink:0;">🔒</div>
            <div>
                <div style="font-weight:700; color:var(--text-main); font-size:0.9rem; margin-bottom:0.25rem;">CSRF Protection</div>
                <div style="font-size:0.82rem; color:var(--text-muted); line-height:1.4;">All state-changing forms are protected with per-session CSRF tokens.</div>
            </div>
        </div>
        <div style="border:1px solid var(--border); padding:1rem; border-radius:10px; display:flex; align-items:flex-start; gap:0.75rem;">
            <div style="font-size:1.4rem; flex-shrink:0;">🖼️</div>
            <div>
                <div style="font-weight:700; color:var(--text-main); font-size:0.9rem; margin-bottom:0.25rem;">Upload Validation</div>
                <div style="font-size:0.82rem; color:var(--text-muted); line-height:1.4;">File uploads validated by MIME type (magic bytes), not just extension.</div>
            </div>
        </div>
        <div style="border:1px solid var(--border); padding:1rem; border-radius:10px; display:flex; align-items:flex-start; gap:0.75rem;">
            <div style="font-size:1.4rem; flex-shrink:0;">📋</div>
            <div>
                <div style="font-weight:700; color:var(--text-main); font-size:0.9rem; margin-bottom:0.25rem;">Audit Logging</div>
                <div style="font-size:0.82rem; color:var(--text-muted); line-height:1.4;">All admin actions, logins, and security events are recorded. <a href="audit.php" style="color:var(--primary);">View log →</a></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
