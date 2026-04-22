<?php
$page_title = 'Users';
require_once 'header.php';

// ─── CSRF helpers ────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

$msg = '';

// ─── All state-mutating actions now require POST + CSRF ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    verifyCsrf();

    $id = (int) $_POST['id'];
    $act = $_POST['action'];
    $sess_id = (int) $_SESSION['user_id']; // OPT 2: cast once, use everywhere — prevents type-juggling

    // BUG FIX #7 / #3: 'make_admin' removed from reachable actions.
    // BUG FIX #1 / #4: All mutations now only reachable via POST.

    if ($act === 'delete' && $id !== $sess_id) {
        // ADJ 3: Both $id and $sess_id are (int)-cast at assignment — strict identity
        // comparison (!==) guarantees an admin can never delete their own session record,
        // even if PHP's loose == would coerce types unexpectedly.
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $sess_id, "Permanently deleted user #" . $id, 'user', $id);
        $msg = "User #$id has been permanently deleted.";

    } elseif ($act === 'verify') {
        $boolT = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE users SET verified = $boolT WHERE id = ?")->execute([$id]);
        auditLog($pdo, $sess_id, "Verified user #$id", 'user', $id);
        $msg = "User #$id verified.";

    } elseif ($act === 'upgrade_pro' || $act === 'upgrade_premium') {
        // BUG FIX #8: Backend tier-guard — prevent downgrading via crafted POST.
        $currentUser = $pdo->prepare("SELECT seller_tier, role FROM users WHERE id = ?");
        $currentUser->execute([$id]);
        $currentUser = $currentUser->fetch();

        $allowedUpgrade = false;
        if ($act === 'upgrade_pro' && $currentUser['seller_tier'] === 'basic')
            $allowedUpgrade = true;
        if ($act === 'upgrade_premium' && in_array($currentUser['seller_tier'], ['basic', 'pro'], true))
            $allowedUpgrade = true;

        if (!$allowedUpgrade || $currentUser['role'] !== 'seller') {
            $msg = "❌ Invalid upgrade path for this user.";
        } else {
            $tier = ($act === 'upgrade_pro') ? 'pro' : 'premium';
            $allTiers = getAccountTiers($pdo);
            $price = (float) ($allTiers[$tier]['price'] ?? 0);

            // FIX 3: Guard against missing tier key entirely before reading 'duration'.
            // If getAccountTiers() returns an incomplete array, fall back to 'forever'
            // rather than hitting an undefined-index notice and passing null to the whitelist.
            $tierData = $allTiers[$tier] ?? [];
            $durStr = isset($tierData['duration']) && $tierData['duration'] !== ''
                ? $tierData['duration']
                : 'forever';

            // Whitelist duration strings — unknown values collapse to NULL safely.
            // ADJ 2: $expire_val is a bound PDO parameter (PHP null or a date string),
            // eliminating the last raw SQL interpolation in this block entirely.
            $allowed_durations = ['forever' => null, '2_weeks' => 14, 'weekly' => 7];
            $days = array_key_exists($durStr, $allowed_durations) ? $allowed_durations[$durStr] : false;
            $expire_val = ($days !== false && $days !== null)
                ? date('Y-m-d H:i:s', strtotime("+{$days} days"))
                : null; // PHP null → PDO sends SQL NULL, zero interpolation

            $pdo->beginTransaction();
            try {
                // ADJ 2: fully parameterised — no $expire_sql string in query.
                $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = ? WHERE id = ?")
                    ->execute([$tier, $expire_val, $id]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?,?,?,?,?,?)")
                    ->execute([
                        $id,
                        'premium',
                        $price,
                        'completed',
                        generateRef(strtoupper(substr($tier, 0, 2))),
                        ucfirst($tier) . " Seller Account Upgrade"
                    ]);

                // OPT 1: auditLog is inside the transaction — if logging throws,
                // rollBack() undoes the UPDATE + INSERT, keeping the paper trail consistent.
                //
                // FIX 2: Audit string uses ASCII "GHS" — NOT the ₵ glyph.
                // Writing ₵ into the DB requires both the connection AND table collation
                // to be utf8mb4; on latin1/utf8 setups it silently stores as mojibake.
                // $msg below keeps ₵ because it only goes into PHP's output buffer, never the DB.
                auditLog(
                    $pdo,
                    $sess_id,
                    "Upgraded user #$id to $tier seller (Revenue: +GHS " . number_format($price, 2) . ")",
                    'user',
                    $id
                );

                $pdo->commit();
                // ₵ is safe here — this string is only echoed to the browser, not stored.
                $msg = "User #$id upgraded to " . ucfirst($tier) . " Seller. Revenue of ₵" . number_format($price, 2) . " recorded.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "❌ Error: " . htmlspecialchars($e->getMessage());
            }
        }

    } elseif ($act === 'downgrade_basic') {
        $pdo->prepare("UPDATE users SET seller_tier = 'basic', tier_expires_at = NULL WHERE id = ?")
            ->execute([$id]);
        auditLog($pdo, $sess_id, "Downgraded user #$id to basic seller", 'user', $id);
        $msg = "User #$id downgraded to Basic Seller.";

    } elseif ($act === 'suspend') {
        $boolT = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE users SET suspended = $boolT WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $sess_id, "Suspended user #$id", 'user', $id);
        $msg = "⛔ User #$id suspended.";

    } elseif ($act === 'reactivate') {
        $boolF = sqlBool(false, $pdo);
        $pdo->prepare("UPDATE users SET suspended = $boolF WHERE id = ?")->execute([$id]);
        auditLog($pdo, $sess_id, "Reactivated user #$id", 'user', $id);
        $msg = "✅ User #$id reactivated.";
    }
}

// ─── Filtering ────────────────────────────────────────────────────────────────
// BUG FIX #2 / #11: Strict allowlist; params array actually used.
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all' => null, 'sellers' => 'seller', 'buyers' => 'buyer', 'admins' => 'admin'];
if (!array_key_exists($filter, $allowed_filters)) {
    $filter = 'all';
}

// BUG FIX #6: Fetch total count separately so "All" button always shows full count.
$total_count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$role_filter = $allowed_filters[$filter];
if ($role_filter !== null) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
    $stmt->execute([$role_filter]);
} else {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
}
$users = $stmt->fetchAll();

// Helper: emit a POST action form-button (replaces plain <a href> for mutations)
function actionBtn(
    string $action,
    int $id,
    string $filter,
    string $label,
    string $cls = 'btn-outline',
    string $confirm = ''
): string {
    $csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
    $action_e = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
    $filter_e = htmlspecialchars($filter, ENT_QUOTES, 'UTF-8');
    // OPT 3: escape label & cls to prevent XSS if values ever come from dynamic sources
    $label_e = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $cls_e = htmlspecialchars($cls, ENT_QUOTES, 'UTF-8');
    $confirm_js = $confirm ? ' onclick="return confirm(' . json_encode($confirm, JSON_HEX_APOS) . ')"' : '';
    return <<<HTML
    <form method="post" style="display:inline;"{$confirm_js}>
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="action"     value="{$action_e}">
        <input type="hidden" name="id"         value="{$id}">
        <input type="hidden" name="filter"     value="{$filter_e}">
        <button type="submit" class="btn {$cls_e} btn-sm">{$label_e}</button>
    </form>
    HTML;
}
?>

<h2 class="mb-2">User Management</h2>

<?php if ($msg): ?>
    <div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex gap-1 mb-3">
    <!-- BUG FIX #6: "All" always shows total_count, not filtered count -->
    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All
        (<?= $total_count ?>)</a>
    <a href="?filter=sellers" class="btn <?= $filter === 'sellers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Sellers</a>
    <a href="?filter=buyers" class="btn <?= $filter === 'buyers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Buyers</a>
    <a href="?filter=admins" class="btn <?= $filter === 'admins' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Admins</a>
</div>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Tier</th>
                <th>Faculty</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $uid = (int) $u['id'];
                $is_suspended = !empty($u['suspended']);
                $is_self = $uid === (int) $_SESSION['user_id']; // OPT 2: strict int comparison
                ?>
                <tr>
                    <td><?= $uid ?></td>
                    <td class="flex gap-1" style="align-items:center;">
                        <?php if ($u['profile_pic']): ?>
                            <img src="../uploads/<?= htmlspecialchars($u['profile_pic']) ?>"
                                style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                        <?php endif; ?>
                        <?= htmlspecialchars($u['username']) ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span
                            class="badge <?= $u['role'] === 'admin' ? 'badge-gold' : ($u['role'] === 'seller' ? 'badge-blue' : '') ?>">
                            <?= ucfirst(htmlspecialchars($u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'seller'): ?>
                            <?= getBadgeHtml($pdo, $u['seller_tier'] ?: 'basic') ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="font-size:0.78rem;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                        title="<?= htmlspecialchars($u['faculty'] ?? '') ?>">
                        <?= htmlspecialchars($u['faculty'] ?? '—') ?>
                    </td>
                    <td>₵<?= number_format($u['balance'], 2) ?></td>
                    <td>
                        <?php if ($is_suspended): ?>
                            <span class="badge badge-rejected">⛔ Suspended</span>
                        <?php elseif ($u['verified']): ?>
                            <span style="color:var(--success);">✓ Verified</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['role'] !== 'admin'): ?>
                            <div class="flex gap-1" style="flex-wrap:wrap;">

                                <?php if (!$u['verified']): ?>
                                    <?= actionBtn('verify', $uid, $filter, 'Verify', 'btn-success') ?>
                                <?php endif; ?>

                                <?php if ($u['role'] === 'seller'):
                                    // ADJ 1: Normalise missing/null tier to 'basic' so the rank
                                    // map always has a valid starting point — no tier falls through.
                                    $tier_rank = ['basic' => 0, 'pro' => 1, 'premium' => 2];
                                    $cur_tier = $u['seller_tier'] ?: 'basic';
                                    $cur_rank = $tier_rank[$cur_tier] ?? 0;
                                    ?>
                                    <?php // Upgrade buttons only render when the user is strictly below that tier ?>
                                    <?php if ($cur_rank < $tier_rank['pro']): ?>
                                        <?= actionBtn('upgrade_pro', $uid, $filter, '🥈 Pro', 'btn-outline') ?>
                                    <?php endif; ?>
                                    <?php if ($cur_rank < $tier_rank['premium']): ?>
                                        <?= actionBtn('upgrade_premium', $uid, $filter, '⭐ Premium', 'btn-gold') ?>
                                    <?php endif; ?>
                                    <?php if ($cur_rank > $tier_rank['basic']): ?>
                                        <?= actionBtn(
                                            'downgrade_basic',
                                            $uid,
                                            $filter,
                                            'D-grade',
                                            'btn-outline',
                                            'Downgrade this seller to Basic?'
                                        ) ?>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($is_suspended): ?>
                                    <?= actionBtn(
                                        'reactivate',
                                        $uid,
                                        $filter,
                                        '✅ Reactivate',
                                        'btn-success',
                                        'Reactivate this user?'
                                    ) ?>
                                <?php else: ?>
                                    <?= actionBtn(
                                        'suspend',
                                        $uid,
                                        $filter,
                                        '⏸ Suspend',
                                        'btn-warn',
                                        'Suspend this user? They will not be able to log in.'
                                    ) ?>
                                <?php endif; ?>

                                <a href="../chat.php?user=<?= $uid ?>" class="btn btn-outline btn-sm">💬 Message</a>

                                <?php if (!$is_self): ?>
                                    <?= actionBtn(
                                        'delete',
                                        $uid,
                                        $filter,
                                        'Delete',
                                        'btn-danger',
                                        'Delete this user permanently? This cannot be undone.'
                                    ) ?>
                                <?php endif; ?>

                            </div>
                        <?php else: ?>
                            <span class="text-muted">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>