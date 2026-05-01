<?php
require_once 'includes/db.php';

// Must be logged in
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

// Admin is exempt
if (($user['role'] ?? '') === 'admin') redirect('admin/');

// Already joined — go to dashboard (terms check happens there)
if (filter_var($user['whatsapp_joined'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (isset($_POST['confirm_joined'])) {
        $inputCode = trim($_POST['verification_code'] ?? '');
        $correctCode = getSiteSetting($pdo, 'whatsapp_verification_code', 'CAMPUS_JOIN_2026');

        if (strtoupper($inputCode) !== strtoupper($correctCode)) {
            $error = "Invalid verification code. Please check the WhatsApp channel description or latest post for the correct code.";
        } else {
            $boolTrue = sqlBool(true, $pdo);
            $stmt = $pdo->prepare("UPDATE users SET whatsapp_joined = $boolTrue WHERE id = ?");
            if ($stmt->execute([$user['id']])) {
                // If terms not yet accepted, go to terms page; else dashboard
                if (!filter_var($user['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    redirect('terms.php');
                }
                redirect('dashboard.php');
            } else {
                $error = "Could not save. Please try again.";
            }
        }
    }
}

$pageTitle = 'Join Our WhatsApp Channel';

// Build a guaranteed-working WhatsApp channel URL
$rawChannelUrl = trim(getSiteSetting($pdo, 'whatsapp_channel_url', 'https://www.whatsapp.com/channel/0029VbCLnKPLY6d7qLGtyQ0Z'));
// Normalize: strip protocol + optional www, then rebuild with https://www.
if (preg_match('#whatsapp\.com/channel/([A-Za-z0-9]+)#', $rawChannelUrl, $m)) {
    $rawChannelUrl = 'https://www.whatsapp.com/channel/' . $m[1];
}

require_once 'includes/header.php';
?>

<style>
.wa-page {
    min-height: calc(100vh - 140px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.wa-card {
    width: 100%;
    max-width: 520px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 28px;
    padding: 2.5rem 2rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
    text-align: center;
}
.wa-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #25d366, #128c7e);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    box-shadow: 0 8px 24px rgba(37,211,102,0.35);
}
.wa-title {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    margin: 0 0 0.6rem;
    color: var(--text-main);
}
.wa-subtitle {
    font-size: 0.95rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0 0 1.75rem;
}
.wa-steps {
    text-align: left;
    background: rgba(37,211,102,0.05);
    border: 1px solid rgba(37,211,102,0.15);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.75rem;
}
.wa-step {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-main);
    line-height: 1.5;
    margin-bottom: 0.75rem;
}
.wa-step:last-child { margin-bottom: 0; }
.wa-step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #25d366;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.wa-join-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.9rem 1.5rem;
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    text-decoration: none;
    margin-bottom: 1rem;
    box-shadow: 0 8px 20px rgba(37,211,102,0.3);
    transition: opacity 0.2s, transform 0.1s;
}
.wa-join-btn:hover { opacity: 0.9; }
.wa-join-btn:active { transform: scale(0.98); }
.wa-confirm-btn {
    width: 100%;
    padding: 0.85rem;
    background: #7c3aed;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    border: none;
    border-radius: 14px;
    cursor: not-allowed;
    opacity: 0.45;
    transition: opacity 0.2s;
    box-shadow: 0 6px 16px rgba(124,58,237,0.25);
}
.wa-confirm-btn.active {
    cursor: pointer;
    opacity: 1;
}
.wa-note {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 1.25rem;
    line-height: 1.5;
}

@media (max-width: 640px) {
    .wa-page { padding: 1rem; min-height: auto !important; }
    .wa-card { padding: 2rem 1.25rem; border-radius: 20px; width: 100% !important; max-width: 100% !important; }
    .wa-title { font-size: 1.4rem; }
    .wa-subtitle { font-size: 0.9rem; margin-bottom: 1.25rem; }
    .wa-steps { padding: 1rem; margin-bottom: 1.5rem; }
}
</style>

<div class="wa-page">
    <div class="wa-card fade-in">
        <!-- WhatsApp icon -->
        <div class="wa-icon">
            <svg width="38" height="38" viewBox="0 0 24 24" fill="white">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </div>

        <h1 class="wa-title">Join Our Channel</h1>
        <p class="wa-subtitle">Stay updated with the latest safety alerts, campus deals, and community announcements.</p>

        <div class="wa-steps">
            <div class="wa-step">
                <div class="wa-step-num">1</div>
                <div>Click the button below to open and <strong>Join</strong> the channel.</div>
            </div>
            <div class="wa-step">
                <div class="wa-step-num">2</div>
                <div>Find the <strong>Verification Code</strong> in the channel description or latest post.</div>
            </div>
            <div class="wa-step">
                <div class="wa-step-num">3</div>
                <div>Enter the code below to unlock your account.</div>
            </div>
        </div>

        <a href="<?= htmlspecialchars($rawChannelUrl, ENT_QUOTES, 'UTF-8') ?>" 
           target="_blank" 
           class="wa-join-btn"
           id="joinBtn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Open WhatsApp Channel
        </a>

        <?php if($error): ?>
            <div class="alert alert-error mb-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div style="margin-bottom:1rem;">
                <input type="text" 
                       name="verification_code" 
                       id="vCode" 
                       class="form-control text-center" 
                       placeholder="Enter Code (e.g. CAMPUS_...)" 
                       required 
                       style="font-family:monospace; font-weight:700; letter-spacing:1px; border-radius:14px; padding:0.9rem;">
            </div>
            <button type="submit" name="confirm_joined" id="confirmBtn" class="wa-confirm-btn">
                Confirm & Continue
            </button>
        </form>

        <p class="wa-note">
            <strong>Note:</strong> Joining the channel is mandatory to ensure all users receive real-time security updates and platform news.
        </p>
    </div>
</div>

<script>
    const vCode = document.getElementById('vCode');
    const confirmBtn = document.getElementById('confirmBtn');
    const joinBtn = document.getElementById('joinBtn');
    let hasClickedJoin = false;

    joinBtn.addEventListener('click', () => {
        hasClickedJoin = true;
    });

    vCode.addEventListener('input', () => {
        if (vCode.value.trim().length > 3) {
            confirmBtn.classList.add('active');
        } else {
            confirmBtn.classList.remove('active');
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
