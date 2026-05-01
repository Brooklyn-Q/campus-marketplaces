<?php
$page_title = 'User Profile';
require_once 'header.php';

$uid = (int) ($_GET['id'] ?? 0);
if ($uid <= 0) {
    echo '<div class="glass"><p>Invalid user ID.</p><a href="users.php" class="btn btn-outline btn-sm">← Back to Users</a></div>';
    require_once 'footer.php';
    exit;
}

// Fetch everything — use * so new columns are picked up automatically
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    echo '<div class="glass"><p>User not found.</p><a href="users.php" class="btn btn-outline btn-sm">← Back to Users</a></div>';
    require_once 'footer.php';
    exit;
}

// Counts / extras (all wrapped individually so one missing table doesn't break the page)
$productCount = 0;
$soldCount = 0;
$buyerOrderCount = 0;
$referralCount = 0;
$totalSpent = 0.0;
$totalEarned = 0.0;
$referredByUser = null;

try { $s = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?"); $s->execute([$uid]); $productCount = (int) $s->fetchColumn(); } catch (Throwable $e) {}
try { $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed'"); $s->execute([$uid]); $soldCount = (int) $s->fetchColumn(); } catch (Throwable $e) {}
try { $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?"); $s->execute([$uid]); $buyerOrderCount = (int) $s->fetchColumn(); } catch (Throwable $e) {}
try { $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?"); $s->execute([$uid]); $referralCount = (int) $s->fetchColumn(); } catch (Throwable $e) {}
try { $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type IN ('purchase','order')"); $s->execute([$uid]); $totalSpent = (float) $s->fetchColumn(); } catch (Throwable $e) {}
try { $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = 'sale'"); $s->execute([$uid]); $totalEarned = (float) $s->fetchColumn(); } catch (Throwable $e) {}
if (!empty($u['referred_by'])) {
    try {
        $s = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $s->execute([(int) $u['referred_by']]);
        $referredByUser = $s->fetchColumn() ?: null;
    } catch (Throwable $e) {}
}

function fmtVal($v): string {
    if ($v === null || $v === '') return '<span style="color:#aaa;font-style:italic;">— Not provided</span>';
    if (is_bool($v)) return $v ? '<span style="color:var(--success);font-weight:700;">✓ Yes</span>' : '<span style="color:#aaa;">✗ No</span>';
    return htmlspecialchars((string) $v);
}
function fmtBool($v): string {
    return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? '<span style="color:var(--success);font-weight:700;">✓ Yes</span>' : '<span style="color:#aaa;">✗ No</span>';
}
function fmtDate($v): string {
    if (!$v) return '<span style="color:#aaa;font-style:italic;">— Never</span>';
    return htmlspecialchars(date('M d, Y H:i', strtotime($v)));
}

$avatarUrl = !empty($u['profile_pic'])
    ? getAssetUrl('uploads/' . htmlspecialchars($u['profile_pic']))
    : (!empty($u['google_avatar']) ? htmlspecialchars($u['google_avatar']) : '');
?>

<style>
.profile-wrap { max-width: 1100px; margin: 0 auto; }
.profile-header { display:flex; align-items:center; gap:1.5rem; padding:1.5rem; margin-bottom:1.5rem; }
.profile-header img { width:96px; height:96px; border-radius:50%; object-fit:cover; border:3px solid var(--primary); }
.profile-header .avatar-fallback { width:96px; height:96px; border-radius:50%; background:linear-gradient(135deg, #7c3aed, #af52de); color:#fff; display:flex; align-items:center; justify-content:center; font-size:2.5rem; font-weight:800; border:3px solid var(--primary); }
.profile-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:1.5rem; }
.profile-card { padding:1.5rem; }
.profile-card h3 { margin:0 0 1rem; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--primary); display:flex; align-items:center; gap:0.5rem; }
.field-row { display:flex; justify-content:space-between; align-items:flex-start; padding:0.6rem 0; border-bottom:1px dashed rgba(128,128,128,0.15); gap:1rem; }
.field-row:last-child { border-bottom:none; }
.field-label { font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.3px; flex-shrink:0; min-width:130px; }
.field-value { font-size:0.88rem; color:var(--text-main); text-align:right; word-break:break-word; }
.stat-pill { display:inline-block; padding:0.35rem 0.8rem; border-radius:999px; font-size:0.75rem; font-weight:700; background:rgba(124,58,237,0.1); color:var(--primary); margin-right:0.3rem; }
</style>

<div class="profile-wrap">
    <div style="margin-bottom:1rem;">
        <a href="users.php" class="btn btn-outline btn-sm">← Back to Users</a>
    </div>

    <div class="glass profile-header">
        <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= htmlspecialchars($u['username']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="avatar-fallback" style="display:none;"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
        <?php else: ?>
            <div class="avatar-fallback"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
        <?php endif; ?>

        <div style="flex:1;">
            <h2 style="margin:0 0 0.25rem; font-size:1.6rem;"><?= htmlspecialchars($u['username']) ?>
                <?php if (!empty($u['verified'])): ?><span class="badge badge-approved" style="font-size:0.65rem;vertical-align:middle;">✓ Verified</span><?php endif; ?>
                <?php if (!empty($u['suspended'])): ?><span class="badge badge-rejected" style="font-size:0.65rem;vertical-align:middle;">⛔ Suspended</span><?php endif; ?>
            </h2>
            <p style="margin:0; color:var(--text-muted); font-size:0.9rem;"><?= htmlspecialchars($u['email']) ?></p>
            <div style="margin-top:0.6rem;">
                <span class="stat-pill"><?= ucfirst(htmlspecialchars($u['role'])) ?></span>
                <?php if ($u['role'] === 'seller'): ?>
                    <span class="stat-pill"><?= ucfirst($u['seller_tier'] ?: 'basic') ?></span>
                <?php endif; ?>
                <span class="stat-pill">₵<?= number_format((float) $u['balance'], 2) ?></span>
                <span class="stat-pill">#<?= $uid ?></span>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.4rem;">
            <a href="messages.php?view=chat&u1=<?= (int) $_SESSION['user_id'] ?>&u2=<?= $uid ?>" class="btn btn-primary btn-sm">Message User</a>
            <a href="users.php" class="btn btn-outline btn-sm">Manage Actions</a>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Registration / Account -->
        <div class="glass profile-card">
            <h3>Registration Details</h3>
            <div class="field-row"><span class="field-label">Username</span><span class="field-value"><?= fmtVal($u['username']) ?></span></div>
            <div class="field-row"><span class="field-label">Email</span><span class="field-value"><?= fmtVal($u['email']) ?></span></div>
            <div class="field-row"><span class="field-label">Role</span><span class="field-value"><?= fmtVal(ucfirst((string) $u['role'])) ?></span></div>
            <div class="field-row"><span class="field-label">Seller Tier</span><span class="field-value"><?= fmtVal(ucfirst((string) ($u['seller_tier'] ?: 'basic'))) ?></span></div>
            <div class="field-row"><span class="field-label">Registered</span><span class="field-value"><?= fmtDate($u['created_at'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Last Seen</span><span class="field-value"><?= fmtDate($u['last_seen'] ?? null) ?></span></div>
        </div>

        <!-- Academic Info -->
        <div class="glass profile-card">
            <h3>Academic Information</h3>
            <div class="field-row"><span class="field-label">Faculty</span><span class="field-value"><?= fmtVal($u['faculty'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Department</span><span class="field-value"><?= fmtVal($u['department'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Level</span><span class="field-value"><?= fmtVal($u['level'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Hall</span><span class="field-value"><?= fmtVal($u['hall'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Hall Residence</span><span class="field-value"><?= fmtVal($u['hall_residence'] ?? null) ?></span></div>
        </div>

        <!-- Contact Info -->
        <div class="glass profile-card">
            <h3>Contact Information</h3>
            <div class="field-row"><span class="field-label">Phone</span><span class="field-value"><?php if (!empty($u['phone'])): ?><a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $u['phone'])) ?>" style="color:inherit;"><?= htmlspecialchars($u['phone']) ?></a><?php else: ?>— Not provided<?php endif; ?></span></div>
            <div class="field-row"><span class="field-label">WhatsApp</span><span class="field-value"><?php if (!empty($u['whatsapp'])): ?><a href="<?= formatWhatsAppLink($u['whatsapp']) ?>" target="_blank" style="color:#25D366;"><?= htmlspecialchars($u['whatsapp']) ?></a><?php else: ?>— Not provided<?php endif; ?></span></div>
            <div class="field-row"><span class="field-label">Instagram</span><span class="field-value"><?php if (!empty($u['instagram'])): ?><a href="https://instagram.com/<?= htmlspecialchars($u['instagram']) ?>" target="_blank" style="color:#E1306C;">@<?= htmlspecialchars($u['instagram']) ?></a><?php else: ?>— Not provided<?php endif; ?></span></div>
            <div class="field-row"><span class="field-label">LinkedIn</span><span class="field-value"><?= fmtVal($u['linkedin'] ?? null) ?></span></div>
        </div>

        <!-- Account Status -->
        <div class="glass profile-card">
            <h3>Account Status</h3>
            <div class="field-row"><span class="field-label">Verified</span><span class="field-value"><?= fmtBool($u['verified'] ?? false) ?></span></div>
            <div class="field-row"><span class="field-label">Suspended</span><span class="field-value"><?= fmtBool($u['suspended'] ?? false) ?></span></div>
            <div class="field-row"><span class="field-label">Terms Accepted</span><span class="field-value"><?= fmtBool($u['terms_accepted'] ?? false) ?></span></div>
            <div class="field-row"><span class="field-label">Accepted At</span><span class="field-value"><?= fmtDate($u['accepted_at'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Vacation Mode</span><span class="field-value"><?= fmtBool($u['vacation_mode'] ?? false) ?></span></div>
            <?php if (array_key_exists('whatsapp_joined', $u)): ?>
                <div class="field-row"><span class="field-label">WhatsApp Joined</span><span class="field-value"><?= fmtBool($u['whatsapp_joined'] ?? false) ?></span></div>
            <?php endif; ?>
            <?php if (array_key_exists('email_verified', $u)): ?>
                <div class="field-row"><span class="field-label">Email Verified</span><span class="field-value"><?= fmtBool($u['email_verified'] ?? false) ?></span></div>
            <?php endif; ?>
            <div class="field-row"><span class="field-label">Tier Expires</span><span class="field-value"><?= fmtDate($u['tier_expires_at'] ?? null) ?></span></div>
        </div>

        <!-- Financials -->
        <div class="glass profile-card">
            <h3>Financials & Activity</h3>
            <div class="field-row"><span class="field-label">Wallet Balance</span><span class="field-value">₵<?= number_format((float) $u['balance'], 2) ?></span></div>
            <div class="field-row"><span class="field-label">Total Earned</span><span class="field-value">₵<?= number_format($totalEarned, 2) ?></span></div>
            <div class="field-row"><span class="field-label">Total Spent</span><span class="field-value">₵<?= number_format($totalSpent, 2) ?></span></div>
            <div class="field-row"><span class="field-label">Products Listed</span><span class="field-value"><?= $productCount ?></span></div>
            <div class="field-row"><span class="field-label">Items Sold</span><span class="field-value"><?= $soldCount ?></span></div>
            <div class="field-row"><span class="field-label">Orders Placed</span><span class="field-value"><?= $buyerOrderCount ?></span></div>
        </div>

        <!-- Referrals -->
        <div class="glass profile-card">
            <h3>Referral Information</h3>
            <div class="field-row"><span class="field-label">Referral Code</span><span class="field-value"><?= fmtVal($u['referral_code'] ?? null) ?></span></div>
            <div class="field-row"><span class="field-label">Referred By</span><span class="field-value"><?= $referredByUser ? htmlspecialchars($referredByUser) : '<span style="color:#aaa;font-style:italic;">— None</span>' ?></span></div>
            <div class="field-row"><span class="field-label">Users Referred</span><span class="field-value"><?= $referralCount ?></span></div>
        </div>

        <?php if (!empty($u['bio'])): ?>
        <!-- Bio / About -->
        <div class="glass profile-card" style="grid-column: 1/-1;">
            <h3>Bio / About</h3>
            <p style="white-space:pre-wrap; line-height:1.7; font-size:0.9rem; margin:0;"><?= htmlspecialchars($u['bio']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
