<?php
// Load DB + session BEFORE header.php to handle POST redirects
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'header.php';

// ---------------------------------------------------------------------------
// Schema bootstrap — run once, safe to call on every request
// ---------------------------------------------------------------------------
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_address     VARCHAR(45)  NOT NULL,
        username_tried VARCHAR(255) NOT NULL DEFAULT '',
        attempt_time   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time)
    )");
} catch (PDOException $e) {
    error_log("login_attempts schema check failed: " . $e->getMessage());
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
const ATTEMPT_LIMIT = 5;
const ATTEMPT_WINDOW = 15;   // minutes
const DUMMY_HASH = '$2y$12$invalidsaltvaluethatisnevertrue000000000000000000000000'; // for timing parity

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Resolve the real client IP address.
 *
 * Only trusts X-Forwarded-For when TRUST_PROXY is explicitly defined as true
 * in your environment config (e.g. when behind a known Cloudflare/nginx proxy).
 * Never blindly reads the header — an attacker can set it to anything.
 */
function get_client_ip(): string
{
    if (
        defined('TRUST_PROXY') && TRUST_PROXY === true
        && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
    ) {
        // The header may be a comma-separated list; take the leftmost (originating) IP
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
        // Validate it looks like a real IP before trusting it
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Returns true if this IP has hit ATTEMPT_LIMIT failures within ATTEMPT_WINDOW.
 */
function is_ip_throttled(PDO $pdo, string $ip): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ?
           AND attempt_time > NOW() - INTERVAL " . ATTEMPT_WINDOW . " MINUTE"
    );
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= ATTEMPT_LIMIT;
}

/**
 * Record a single failed attempt.
 */
function record_failed_attempt(PDO $pdo, string $ip, string $username_tried): void
{
    $pdo->prepare(
        "INSERT INTO login_attempts (ip_address, username_tried, attempt_time)
         VALUES (?, ?, NOW())"
    )->execute([$ip, $username_tried]);
}

/**
 * Clear all attempts for this IP on successful login.
 */
function clear_attempts(PDO $pdo, string $ip): void
{
    $pdo->prepare(
        "DELETE FROM login_attempts WHERE ip_address = ?"
    )->execute([$ip]);
}

/**
 * Purge attempts older than the window (lightweight housekeeping on every login).
 * Prevents the table growing unboundedly without needing a cron job.
 */
function purge_old_attempts(PDO $pdo): void
{
    $pdo->exec(
        "DELETE FROM login_attempts
         WHERE attempt_time < NOW() - INTERVAL " . ATTEMPT_WINDOW . " MINUTE"
    );
}

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
// Main login logic
// ---------------------------------------------------------------------------
$err = '';
$ip = get_client_ip();

// Lightweight housekeeping — trim stale rows without a dedicated cron
purge_old_attempts($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Step 1 — Check throttle BEFORE hitting the users table
    if (is_ip_throttled($pdo, $ip)) {
        auditLogWithContext(
            $pdo,
            0,
            "Blocked login from throttled IP: $ip (tried username: " . htmlspecialchars($username) . ")",
            'security',
            0
        );
        $err = "Too many failed login attempts. Please wait " . ATTEMPT_WINDOW . " minutes and try again.";

    } else {
        // Step 2 — Fetch the user record (admin only, or adapt to your schema)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Step 3 — Always run a password_verify() to prevent timing attacks.
        // If the username doesn't exist we verify against DUMMY_HASH so the
        // response time is indistinguishable from a real (wrong) password check.
        $hash_to_check = $user ? $user['password'] : DUMMY_HASH;
        $password_ok = password_verify($password, $hash_to_check);

        if ($user && $password_ok) {
            // --- Successful login ---
            clear_attempts($pdo, $ip);

            // Rotate the session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            auditLogWithContext(
                $pdo,
                $user['id'],
                "Successful login for '{$user['username']}'",
                'auth',
                $user['id']
            );

            $redirect = isAdmin() ? 'index.php' : '../index.php';
            header("Location: $redirect");
            exit;

        } else {
            // --- Failed login ---
            record_failed_attempt($pdo, $ip, $username);

            // Count remaining attempts so the admin knows how many are left
            $stmt2 = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE ip_address = ?
                   AND attempt_time > NOW() - INTERVAL " . ATTEMPT_WINDOW . " MINUTE"
            );
            $stmt2->execute([$ip]);
            $attempts_so_far = (int) $stmt2->fetchColumn();
            $remaining = max(0, ATTEMPT_LIMIT - $attempts_so_far);

            auditLogWithContext(
                $pdo,
                0,
                "Failed login attempt from IP $ip (tried username: " . htmlspecialchars($username) . ")",
                'security',
                0
            );

            // Generic message — never reveal whether username or password was wrong
            $err = $remaining > 0
                ? "Invalid credentials. $remaining attempt(s) remaining before your IP is throttled."
                : "Too many failed login attempts. Please wait " . ATTEMPT_WINDOW . " minutes and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
</head>

<body>

    <div style="max-width:400px; margin:4rem auto;">
        <h2 class="mb-3">🔐 Admin Login</h2>

        <?php if ($err): ?>
            <div class="alert alert-error fade-in"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!--
        Button is disabled immediately on submit to prevent double-submit.
        Re-enabled after 8 s as a fallback in case the server is slow —
        prevents the admin being permanently stuck without a page reload.
    -->
        <form method="POST" onsubmit="
              const btn = this.querySelector('button[type=submit]');
              btn.disabled = true;
              setTimeout(() => btn.disabled = false, 8000);
              return true;">
            <?= csrf_field() ?>

            <div class="mb-2">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required style="width:100%;">
            </div>

            <div class="mb-3">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required
                    style="width:100%;">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.9rem;">
                Login
            </button>
        </form>
    </div>

</body>

</html>
<?php require_once 'footer.php'; ?>