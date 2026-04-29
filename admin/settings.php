<?php
// IMPORTANT: Load DB + session FIRST, run all login logic, then conditionally
// include header.php — header.php redirects non-admins, so it must only be
// loaded AFTER a successful login or when the user is already an admin.
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------------------------------------------------------------------
// Schema bootstrap + rate-limiting helpers are defined in db.php (shared).
// Aliases kept here so the rest of this file's code doesn't need changing.
// ---------------------------------------------------------------------------
ensure_login_attempts_table($pdo);

const ATTEMPT_LIMIT  = LOGIN_ATTEMPT_LIMIT;
const ATTEMPT_WINDOW = LOGIN_ATTEMPT_WINDOW;
const DUMMY_HASH     = LOGIN_DUMMY_HASH;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Thin wrappers so the login logic below keeps its original function names.
function get_client_ip(): string        { return get_login_client_ip(); }
function is_ip_throttled(PDO $p, string $ip): bool          { return is_login_throttled($p, $ip); }
function record_failed_attempt(PDO $p, string $ip, string $u): void { record_login_failure($p, $ip, $u); }
function clear_attempts(PDO $p, string $ip): void           { clear_login_attempts($p, $ip); }
function purge_old_attempts(PDO $p): void                   { purge_old_login_attempts($p); }

/**
 * Extended audit log that captures IP + User-Agent for high-stakes events.
 * Falls back gracefully if the columns don't exist yet in older schemas.
 */
function auditLogWithContext(
    PDO $pdo,
    int $user_id,
    string $action,
    string $type = 'system',
    int $target = 0
): void {
    $ip = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Attempt the richer insert; fall back to the base auditLog() if the
    // extra columns aren't present yet (e.g. during a rolling deployment)
    try {
        $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, type, target_id, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$user_id, $action, $type, $target, $ip, $user_agent]);
    } catch (PDOException $e) {
        // Columns not present — fall back to the standard helper
        auditLog($pdo, $user_id, $action, $type, $target);
        error_log("auditLogWithContext fell back to auditLog(): " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Main login logic — runs BEFORE header.php so redirects don't block it
// ---------------------------------------------------------------------------
$err = '';
$ip = get_client_ip();
$googleEnabled = googleSignInEnabled();

purge_old_attempts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (is_ip_throttled($pdo, $ip)) {
        auditLogWithContext($pdo, 0, "Blocked login from throttled IP: $ip", 'security', 0);
        $err = "Too many failed login attempts. Please wait " . ATTEMPT_WINDOW . " minutes and try again.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (LOWER(email) = LOWER(?) OR username = ?) AND role = 'admin' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        $hash_to_check = $user ? $user['password'] : DUMMY_HASH;
        $password_ok   = password_verify($password, $hash_to_check);

        if ($user && $password_ok) {
            clear_attempts($pdo, $ip);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            auditLogWithContext($pdo, $user['id'], "Admin login: '{$user['username']}'", 'auth', $user['id']);
            header('Location: index.php');
            exit;
        } else {
            record_failed_attempt($pdo, $ip, $username);
            $intervalSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                ? "INTERVAL '" . ATTEMPT_WINDOW . " minutes'"
                : "INTERVAL " . ATTEMPT_WINDOW . " MINUTE";
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > NOW() - $intervalSql");
            $stmt2->execute([$ip]);
            $remaining = max(0, ATTEMPT_LIMIT - (int) $stmt2->fetchColumn());
            auditLogWithContext($pdo, 0, "Failed admin login from $ip (tried: $username)", 'security', 0);
            $err = $remaining > 0
                ? "Invalid credentials. $remaining attempt(s) remaining before your IP is throttled."
                : "Too many failed login attempts. Please wait " . ATTEMPT_WINDOW . " minutes and try again.";
        }
    }
}

// If not yet an admin, show the login form and stop — do NOT load header.php
if (!isAdmin()) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            background: #0f0f1a;
            display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .login-card {
            background: #1a1a2e; border: 1px solid rgba(124,58,237,0.3);
            border-radius: 16px; padding: 2.5rem 2rem;
            width: 100%; max-width: 400px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
        }
        h2 { color: #fff; font-size: 1.5rem; font-weight: 800; margin: 0 0 0.25rem; text-align: center; }
        .subtitle { color: rgba(255,255,255,0.5); font-size: 0.88rem; text-align: center; margin: 0 0 1.75rem; }
        .error {
            background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3);
            color: #f87171; padding: 0.75rem 1rem; border-radius: 8px;
            font-size: 0.85rem; margin-bottom: 1.25rem;
        }
        label { display: block; color: rgba(255,255,255,0.7); font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; }
        input[type=text], input[type=password] {
            width: 100%; padding: 0.7rem 0.9rem; border-radius: 8px;
            border: 1px solid rgba(124,58,237,0.3); background: rgba(255,255,255,0.05);
            color: #fff; font-size: 0.9rem; outline: none;
            transition: border-color 0.2s;
        }
        input[type=text]:focus, input[type=password]:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.2);
        }
        .field { margin-bottom: 1rem; }
        .btn-login {
            width: 100%; padding: 0.85rem; margin-top: 0.5rem;
            background: #7c3aed; color: #fff; border: none;
            border-radius: 8px; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-login:hover { background: #6d28d9; }
        .btn-login:disabled { opacity: 0.6; cursor: not-allowed; }
        .forgot { text-align: right; margin-top: 0.75rem; }
        .forgot a { color: #a78bfa; font-size: 0.82rem; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🔐 Admin Login</h2>
        <p class="subtitle">Campus Marketplace Administration</p>

        <?php if ($err): ?>
            <div class="error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="const b=this.querySelector('button');b.disabled=true;setTimeout(()=>b.disabled=false,8000);">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_login" value="1">

            <div class="field">
                <label for="username">Email or Username</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="admin@example.com">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="forgot">
            <a href="../forgot_password.php">Forgot password?</a>
        </div>

        <?php if ($googleEnabled): ?>
            <div style="display:flex;align-items:center;gap:12px;margin:1.5rem 0;">
                <div style="height:1px;background:rgba(255,255,255,0.1);flex:1;"></div>
                <span style="font-size:0.75rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.08em;">or</span>
                <div style="height:1px;background:rgba(255,255,255,0.1);flex:1;"></div>
            </div>
            <div id="googleAdminLoginButton" style="display:flex;justify-content:center;"></div>
            <form id="googleAdminLoginForm" method="POST" action="../google_signin.php" style="display:none;">
                <input type="hidden" name="credential" id="googleAdminLoginCredential">
                <input type="hidden" name="mode" value="admin">
            </form>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <script>
                function handleGoogleAdminLogin(r) {
                    const i = document.getElementById('googleAdminLoginCredential');
                    if (!r || !r.credential || !i) return;
                    i.value = r.credential;
                    document.getElementById('googleAdminLoginForm').submit();
                }
                window.addEventListener('load', function () {
                    if (!window.google || !google.accounts) return;
                    google.accounts.id.initialize({
                        client_id: <?= json_encode(env('GOOGLE_CLIENT_ID', '')) ?>,
                        callback: handleGoogleAdminLogin
                    });
                    google.accounts.id.renderButton(
                        document.getElementById('googleAdminLoginButton'),
                        { theme: 'outline', size: 'large', shape: 'pill', text: 'continue_with', width: 320 }
                    );
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
    exit; // Stop here — never load the admin panel
}

// User is an admin — now safe to load the admin header and settings page
$page_title = 'Settings';
require_once 'header.php';
?>
