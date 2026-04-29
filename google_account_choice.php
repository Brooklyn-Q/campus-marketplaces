<?php
require_once 'includes/google_auth.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');
}

if (!googleSignInEnabled()) {
    setFlashMessage('auth_error', 'Google sign-in is not configured yet.');
    redirect('login.php');
}

$pendingGoogle = getPendingGoogleSignup();
if (!$pendingGoogle) {
    setFlashMessage('auth_error', 'Your Google sign-in session expired. Please try again.');
    redirect('login.php');
}

$error = getFlashMessage('auth_error');
$success = getFlashMessage('auth_success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $role = strtolower(trim($_POST['role'] ?? ''));
    if (!in_array($role, ['buyer', 'seller'], true)) {
        $error = 'Please choose whether you want a buyer or seller account.';
    } else {
        $existing = findGoogleLinkedUser($pdo, $pendingGoogle);
        if ($existing) {
            $existing = linkGoogleIdentityToUser($pdo, $existing, $pendingGoogle);
            if ($existing) {
                startGoogleUserSession($pdo, $existing, false);
            }
        }

        $user = createGoogleMarketplaceUser($pdo, $pendingGoogle, $role);
        if (!$user) {
            $error = 'We could not finish creating your Google account. Please try again.';
        } else {
            startGoogleUserSession($pdo, $user, true);
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding:20px;">
    <div class="glass form-container fade-in" style="width:100%; max-width:720px; box-shadow:0 32px 80px rgba(0,0,0,0.12);">
        <div class="text-center" style="margin-bottom:2rem;">
            <h1 style="font-size:2rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Choose Your Account Type</h1>
            <p style="color:var(--text-muted); font-size:1rem; margin-top:0.6rem;">
                We verified your Google account for <strong><?= htmlspecialchars($pendingGoogle['email'], ENT_QUOTES, 'UTF-8') ?></strong>.
                Now choose how you want to use Campus Marketplace.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error fade-in" style="text-align:center; margin-bottom:1.5rem;">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success fade-in" style="text-align:center; margin-bottom:1.5rem;">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; margin-bottom:1.5rem;">
                <label style="display:block; cursor:pointer;">
                    <input type="radio" name="role" value="buyer" style="display:none;" checked>
                    <div style="border:1px solid rgba(124,58,237,0.12); border-radius:24px; padding:1.5rem; background:rgba(124,58,237,0.03); height:100%;">
                        <div style="font-size:1.2rem; font-weight:800; margin-bottom:0.5rem;">Buyer Account</div>
                        <p style="margin:0; color:var(--text-muted); line-height:1.6;">
                            Browse products, place orders, chat with sellers, and track your purchases.
                        </p>
                    </div>
                </label>
                <label style="display:block; cursor:pointer;">
                    <input type="radio" name="role" value="seller" style="display:none;">
                    <div style="border:1px solid rgba(124,58,237,0.12); border-radius:24px; padding:1.5rem; background:rgba(124,58,237,0.03); height:100%;">
                        <div style="font-size:1.2rem; font-weight:800; margin-bottom:0.5rem;">Seller Account</div>
                        <p style="margin:0; color:var(--text-muted); line-height:1.6;">
                            List products, manage orders, talk to buyers, and grow your campus shop.
                        </p>
                    </div>
                </label>
            </div>

            <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" style="min-width:220px; justify-content:center;">Continue with Selected Account</button>
                <a href="login.php" class="btn" style="min-width:180px; justify-content:center; text-decoration:none;">Back to Sign In</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radioCards = document.querySelectorAll('input[name="role"]');
        const applyState = () => {
            radioCards.forEach((radio) => {
                const card = radio.closest('label')?.querySelector('div');
                if (!card) return;
                if (radio.checked) {
                    card.style.borderColor = 'rgba(124,58,237,0.35)';
                    card.style.boxShadow = '0 16px 32px rgba(124,58,237,0.12)';
                    card.style.background = 'rgba(124,58,237,0.08)';
                } else {
                    card.style.borderColor = 'rgba(124,58,237,0.12)';
                    card.style.boxShadow = 'none';
                    card.style.background = 'rgba(124,58,237,0.03)';
                }
            });
        };
        radioCards.forEach((radio) => {
            radio.addEventListener('change', applyState);
            radio.closest('label')?.addEventListener('click', () => {
                radio.checked = true;
                applyState();
            });
        });
        applyState();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
