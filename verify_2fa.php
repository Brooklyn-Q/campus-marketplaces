<?php
require_once 'includes/db.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');
}

$pendingUserId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
$pendingUsername = (string) ($_SESSION['pending_2fa_username'] ?? '');
$pendingIp = (string) ($_SESSION['pending_2fa_ip'] ?? '');
$pendingRole = (string) ($_SESSION['pending_2fa_role'] ?? '');
$currentIp = get_login_client_ip();

if ($pendingUserId <= 0 || $pendingIp === '' || $pendingIp !== $currentIp) {
    session_unset();
    session_destroy();
    redirect('login.php?expired=1');
}

$stmt = $pdo->prepare("SELECT id, username, role, totp_secret, totp_enabled, whatsapp_joined FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user) {
    session_unset();
    session_destroy();
    redirect('login.php');
}

$error = '';
$totpEnabled = filter_var($user['totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
$existingSecret = trim((string) ($user['totp_secret'] ?? ''));

// For users, 2FA is optional. If they reach this page, totp_enabled must be true.
if (!$totpEnabled || $existingSecret === '') {
    // This shouldn't happen if login.php logic is correct, but safety first.
    session_unset();
    session_destroy();
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));

    if ($existingSecret === '' || !verify_totp_code($existingSecret, $code)) {
        $error = 'Invalid authentication code. Please try again.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = (string) $user['role'];
        
        // Update last seen
        try {
            $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
        } catch (PDOException $e) {}

        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_ip'], $_SESSION['pending_2fa_role']);
        
        // Check WhatsApp join status for regular users
        $isAdmin = ($user['role'] === 'admin');
        $hasJoined = filter_var($user['whatsapp_joined'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$isAdmin && !$hasJoined) {
            redirect('whatsapp_join.php');
        }
        
        redirect($isAdmin ? 'admin/' : 'dashboard.php');
    }
}

require_once 'includes/header.php';
?>

<div class="auth-page-root" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--auth-bg);color:var(--auth-text);padding:1rem;">
    <div class="auth-card-new" style="width:100%;max-width:440px;">
        <div class="auth-header">
            <div class="auth-icon-wrap">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h2 class="auth-title">Two-Factor Auth</h2>
            <p class="auth-subtitle">Enter the 6-digit code from your app.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="auth-alert auth-alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="auth-field">
                <label for="code" class="auth-label">Authenticator code</label>
                <div class="auth-input-wrap">
                    <div class="auth-input-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3c1.72 0 3.347.433 4.774 1.2m0 0a10 10 0 014.543 8.341m-4.543-8.341l.053.09m-11.398 4.36a10.001 10.001 0 004.582 9.259m0 0a10.004 10.004 0 01-4.582-9.259M12 11a3 3 0 11-6 0 3 3 0 016 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <input id="code" name="code" class="auth-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="000000">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;height:44px;justify-content:center;font-weight:700;">
                Verify and Sign In
            </button>
        </form>

        <div style="margin-top:1.5rem;text-align:center;border-top:1px solid var(--auth-border);padding-top:1.2rem;">
            <a href="logout.php" style="color:var(--auth-muted);text-decoration:none;font-size:0.875rem;font-weight:500;display:inline-flex;align-items:center;gap:0.5rem;transition:color 0.2s;" onmouseover="this.style.color='var(--auth-text)'" onmouseout="this.style.color='var(--auth-muted)'">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                Back to Login
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
