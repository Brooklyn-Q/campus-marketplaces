<?php
require_once '../includes/db.php';
if (!isAdmin()) {
    header('Location: /login.php?mode=admin');
    exit;
}
if (!is_admin_ip_allowed()) {
    http_response_code(403);
    exit('Access denied: your IP is not allowed for admin access.');
}
$admin2faVerified = filter_var($_SESSION['admin_2fa_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$admin2faVerified) {
    header('Location: /admin/verify_2fa.php');
    exit;
}
$page_title = $page_title ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Admin</title>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/dist/app.css">
    <script type="module" src="../assets/dist/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
    <style>
        .container { max-width: none !important; width: 96% !important; padding-left: 2rem; padding-right: 2rem; }
    </style>
</head>
<body>
    <nav style="position:sticky; top:0; z-index:9999; backdrop-filter:saturate(180%) blur(24px); -webkit-backdrop-filter:saturate(180%) blur(24px); background:rgba(255,255,255,0.85); border-bottom:1px solid var(--border); transition: all 0.3s ease; padding: 0 4%;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:58px; max-width:none; margin:0 auto; width:100%;">
            <a href="index.php" style="font-size:1.05rem; font-weight:800; color:var(--text-main); text-decoration:none; letter-spacing:-0.04em; display:flex; align-items:center; gap:6px; flex-shrink:0; transition:opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Admin Panel
            </a>
            <div class="nav-links" style="display:flex; gap:0.8rem; align-items:center; justify-content:center; flex:1; min-width:0;">
                <a href="index.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Dashboard</a>
                <a href="users.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Users</a>
                <a href="products.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Moderation</a>
                <a href="messages.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Messages</a>
                <a href="audit.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Audit Log</a>
                <a href="analytics.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">📊 Analytics</a>
                <a href="ads.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">📢 Ads</a>
                <a href="settings.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.5rem 0.8rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Settings</a>
                <a href="../index.php" style="background:#7c3aed; color:#fff; font-weight:700; font-size:0.95rem; padding:0.5rem 1.2rem; border-radius:980px; text-decoration:none; white-space:nowrap; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">Exit to App</a>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                <div id="react-theme-toggle"></div>
                <button class="mobile-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')" style="color:var(--text-main); cursor:pointer; background:none; border:none; padding:6px; border-radius:8px; display:none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </div>
    </nav>
    <div class="container">
