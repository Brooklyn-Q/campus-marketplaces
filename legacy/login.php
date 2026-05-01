<?php
require_once 'includes/db.php';
if (isLoggedIn()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Case-sensitive username check but insensitive email check
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR username = ?");
        $stmt->execute([$login_id, $login_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is suspended
            if (!empty($user['suspended'])) {
                $error = "⛔ Your account has been suspended. Contact admin for assistance.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                // Best-effort last_seen update. Do not block login on row locks.
                try {
                    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (PDOException $e) {
                    $errorCode = (int)($e->errorInfo[1] ?? 0);
                    if ($errorCode !== 1205 && $errorCode !== 1213) {
                        throw $e;
                    }
                }
                redirect('dashboard.php');
            }
        } else {
            $error = "Invalid email/username or password.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding: 20px;">
    <div class="glass form-container fade-in" style="width:100%; max-width:480px; box-shadow:0 32px 80px rgba(0,0,0,0.12);">
        <div class="text-center" style="margin-bottom:2.5rem;">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:22px; background:linear-gradient(135deg, rgba(124,58,237,0.12), rgba(124,58,237,0.06)); margin-bottom:1.25rem; border:1px solid rgba(124,58,237,0.1);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <h1 style="font-size:2rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Welcome Back</h1>
            <p style="color:var(--text-muted); font-size:1.05rem; margin-top:0.5rem; font-weight:500;">Access your safe campus marketplace</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-error fade-in" style="text-align:center; margin-bottom:1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="login_id">Email or Username</label>
                <input type="text" name="login_id" id="login_id" class="form-control" placeholder="Enter your identifier" required autocomplete="username">
            </div>
            <div class="form-group" style="margin-bottom:2rem;">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:1.1rem; font-size:1.05rem; font-weight:700; box-shadow:0 10px 30px rgba(124,58,237,0.2);">Sign In</button>
        </form>

        <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid rgba(0,0,0,0.06); text-align:center;">
            <p style="font-size:0.95rem; color:var(--text-muted); margin:0;">
                Don't have an account? <a href="register.php" style="color:var(--primary); font-weight:700;">Join now</a>
            </p>
        </div>
    </div>
</div>

<style>
    @media (max-width: 600px) {
        .form-container {
            width: 95% !important;
            padding: 2rem 1.5rem !important;
            margin: 1rem auto !important;
        }
        h1 { font-size: 1.6rem !important; }
        p { font-size: 0.95rem !important; }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
