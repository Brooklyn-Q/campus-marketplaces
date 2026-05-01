<?php
require_once '../includes/db.php';

if (isAdmin() && !empty($_SESSION['admin_2fa_verified'])) {
    redirect('index.php');
}

$pendingUserId = (int) ($_SESSION['pending_admin_2fa_user_id'] ?? 0);
$pendingUsername = (string) ($_SESSION['pending_admin_2fa_username'] ?? '');
$pendingIp = (string) ($_SESSION['pending_admin_2fa_ip'] ?? '');
$currentIp = get_login_client_ip();

if ($pendingUserId <= 0 || $pendingIp === '' || $pendingIp !== $currentIp) {
    session_unset();
    session_destroy();
    redirect('login.php?expired=1');
}

ensure_admin_2fa_schema($pdo);
$stmt = $pdo->prepare("SELECT id, username, role, admin_totp_secret, admin_totp_enabled FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    session_unset();
    session_destroy();
    redirect('login.php');
}

$error = '';
$setupSecret = '';
$totpEnabled = filter_var($user['admin_totp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
$existingSecret = trim((string) ($user['admin_totp_secret'] ?? ''));

if (!$totpEnabled || $existingSecret === '') {
    $setupSecret = (string) ($_SESSION['pending_admin_2fa_setup_secret'] ?? '');
    if ($setupSecret === '') {
        $setupSecret = generate_totp_secret();
        $_SESSION['pending_admin_2fa_setup_secret'] = $setupSecret;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    $verifySecret = $totpEnabled ? $existingSecret : $setupSecret;

    if ($verifySecret === '' || !verify_totp_code($verifySecret, $code)) {
        $error = 'Invalid authentication code. Please try again.';
    } else {
        if (!$totpEnabled) {
            $pdo->prepare("UPDATE users SET admin_totp_secret = ?, admin_totp_enabled = ? WHERE id = ?")
                ->execute([$setupSecret, 1, $pendingUserId]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_2fa_verified'] = true;
        $_SESSION['admin_last_activity'] = time();

        unset($_SESSION['pending_admin_2fa_user_id'], $_SESSION['pending_admin_2fa_username'], $_SESSION['pending_admin_2fa_ip'], $_SESSION['pending_admin_2fa_setup_secret']);
        redirect('index.php');
    }
}

$issuer = rawurlencode('Campus Marketplace');
$account = rawurlencode($pendingUsername !== '' ? $pendingUsername : 'admin');
$otpauthUrl = $setupSecret !== ''
    ? "otpauth://totp/{$issuer}:{$account}?secret={$setupSecret}&issuer={$issuer}&digits=6&period=30"
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin 2FA Verification</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f1222;color:#fff;padding:1rem;">
    <div style="width:100%;max-width:520px;background:#171b31;border:1px solid #2d3564;border-radius:16px;padding:1.5rem;">
        <h2 style="margin:0 0 0.5rem;">Admin 2FA Verification</h2>
        <p style="margin:0 0 1rem;color:#b8c1ff;">Enter your 6-digit authenticator code to continue.</p>

        <?php if ($setupSecret !== ''): ?>
            <div style="background:#0f1533;border:1px solid #32428a;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;text-align:center;">
                <p style="margin:0 0 1rem;font-weight:700;font-size:1.1rem;color:#fff;">Two-Factor Authentication Setup</p>
                
                <div style="margin-bottom:1.2rem;">
                    <div id="qrcode" style="display:inline-block;padding:12px;background:#fff;border-radius:12px;box-shadow: 0 4px 12px rgba(0,0,0,0.3);"></div>
                </div>
                
                <p style="margin:0 0 0.6rem;font-size:0.9rem;color:#c7cffd;">Scan the QR code or enter this key manually:</p>
                <code style="display:block;word-break:break-all;background:#0a1028;padding:0.8rem;border-radius:8px;font-family:monospace;font-size:1rem;color:#7da3ff;border:1px solid #1e2a5a;"><?= htmlspecialchars($setupSecret, ENT_QUOTES, 'UTF-8') ?></code>
                
                <p style="margin:1rem 0 0;font-size:0.85rem;color:#8f99c7;">
                    Can't scan? <a href="<?= htmlspecialchars($otpauthUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:#7da3ff;text-decoration:none;font-weight:600;">Open in app directly</a>
                </p>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: <?= json_encode($otpauthUrl) ?>,
                    width: 160,
                    height: 160,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            </script>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div style="background:#3a1220;border:1px solid #8f2b47;color:#ffb3c3;padding:0.7rem;border-radius:10px;margin-bottom:0.8rem;">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <label for="code" style="display:block;margin-bottom:0.4rem;">Authenticator code</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                   style="width:100%;padding:0.8rem;border-radius:10px;border:1px solid #3a4272;background:#0f1533;color:#fff;">
            <button type="submit" style="margin-top:0.9rem;width:100%;padding:0.85rem;border:none;border-radius:10px;background:#5865f2;color:#fff;font-weight:700;cursor:pointer;transition:all 0.2s;" 
                    onmouseover="this.style.background='#4752c4'" onmouseout="this.style.background='#5865f2'">
                Verify and continue
            </button>
        </form>

        <div style="margin-top:1.5rem;text-align:center;border-top:1px solid #2d3564;padding-top:1.2rem;">
            <a href="../logout.php" style="color:#8f99c7;text-decoration:none;font-size:0.9rem;font-weight:500;display:inline-flex;align-items:center;gap:0.5rem;transition:color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#8f99c7'">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                Back to Login
            </a>
        </div>
    </div>
</body>
</html>
