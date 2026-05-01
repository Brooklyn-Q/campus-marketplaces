<?php
require_once 'includes/db.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');
}

$selector = trim($_GET['selector'] ?? $_POST['selector'] ?? '');
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';

$resetRecord = ($selector !== '') ? findPasswordResetToken($pdo, $selector) : null;

if (!$resetRecord || !empty($resetRecord['used_at']) || strtotime((string) $resetRecord['expires_at']) < time()) {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    check_csrf();
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
        $error = 'Password must be at least 12 characters and include uppercase, lowercase, number, and special character.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!hash_equals((string) $resetRecord['token_hash'], hash('sha256', $token))) {
        $error = 'This reset link could not be verified.';
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, auth_provider = COALESCE(auth_provider, 'local') WHERE id = ?")
            ->execute([$newHash, $resetRecord['user_id']]);
        consumePasswordResetToken($pdo, $selector);
        $success = 'Your password has been reset. You can sign in now.';
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding:20px;">
    <div class="glass form-container fade-in" style="width:100%; max-width:500px;">
        <div class="text-center" style="margin-bottom:2rem;">
            <h1 style="font-size:1.9rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Set New Password</h1>
            <p style="color:var(--text-muted); margin-top:0.5rem;">Choose a strong password you’ll remember.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:1rem; text-align:center;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:1rem; text-align:center;">
                <?= htmlspecialchars($success) ?>
                <div style="margin-top:0.8rem;"><a href="login.php" class="btn btn-primary">Go to Sign In</a></div>
            </div>
        <?php elseif ($error === ''): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="selector" value="<?= htmlspecialchars($selector) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="12" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="12" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Save New Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
