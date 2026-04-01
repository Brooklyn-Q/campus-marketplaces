<?php
$page_title = 'Users';
require_once 'header.php';

$msg = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $act = $_GET['action'];

    if ($act === 'delete' && $id != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Permanently deleted user #$id", 'user', $id);
        $msg = "User #$id has been permanently deleted. Their ID is now available for reuse.";
    } elseif ($act === 'make_admin') {
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Promoted user #$id to admin", 'user', $id);
        $msg = "User #$id promoted to admin.";
    } elseif ($act === 'verify') {
        $pdo->prepare("UPDATE users SET verified = 1 WHERE id = ?")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Verified user #$id", 'user', $id);
        $msg = "User #$id verified.";
    } elseif ($act === 'upgrade_pro' || $act === 'upgrade_premium') {
        $tier = ($act === 'upgrade_pro') ? 'pro' : 'premium';
        $allTiers = getAccountTiers($pdo);
        $price = (float)($allTiers[$tier]['price'] ?? 0);
        $durStr = $allTiers[$tier]['duration'] ?? 'forever';
        $expire_sql = "NULL";
        if($durStr === '2_weeks') $expire_sql = "DATE_ADD(NOW(), INTERVAL 14 DAY)";
        if($durStr === 'weekly') $expire_sql = "DATE_ADD(NOW(), INTERVAL 7 DAY)";
        
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = $expire_sql WHERE id = ?")->execute([$tier, $id]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?,?,?,?,?,?)")
                ->execute([$id, 'premium', $price, 'completed', generateRef(strtoupper(substr($tier,0,2))), ucfirst($tier) . " Seller Account Upgrade"]);
            
            auditLog($pdo, $_SESSION['user_id'], "Upgraded user #$id to $tier seller (Revenue: +₵$price)", 'user', $id);
            $pdo->commit();
            $msg = "User #$id upgraded to " . ucfirst($tier) . " Seller. Revenue of ₵$price recorded.";
        } catch(Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Error: " . $e->getMessage();
        }
    } elseif ($act === 'downgrade_basic') {
        $pdo->prepare("UPDATE users SET seller_tier = 'basic' WHERE id = ?")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Downgraded user #$id to basic seller", 'user', $id);
        $msg = "User #$id downgraded to Basic Seller.";
    } elseif ($act === 'suspend') {
        $pdo->prepare("UPDATE users SET suspended = 1 WHERE id = ? AND role != 'admin'")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Suspended user #$id", 'user', $id);
        $msg = "⛔ User #$id suspended.";
    } elseif ($act === 'reactivate') {
        $pdo->prepare("UPDATE users SET suspended = 0 WHERE id = ?")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], "Reactivated user #$id", 'user', $id);
        $msg = "✅ User #$id reactivated.";
    }
}

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($filter === 'sellers') { $query .= " AND role='seller'"; }
elseif ($filter === 'buyers') { $query .= " AND role='buyer'"; }
elseif ($filter === 'admins') { $query .= " AND role='admin'"; }
$query .= " ORDER BY created_at DESC";
$users = $pdo->prepare($query); $users->execute($params); $users = $users->fetchAll();
?>

<h2 class="mb-2">User Management</h2>

<?php if($msg): ?><div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="flex gap-1 mb-3">
    <a href="?filter=all" class="btn <?= $filter==='all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All (<?= count($users) ?>)</a>
    <a href="?filter=sellers" class="btn <?= $filter==='sellers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Sellers</a>
    <a href="?filter=buyers" class="btn <?= $filter==='buyers' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Buyers</a>
    <a href="?filter=admins" class="btn <?= $filter==='admins' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Admins</a>
</div>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto;">
    <table>
        <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Role</th><th>Tier</th><th>Faculty</th><th>Balance</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td class="flex gap-1" style="align-items:center;">
                    <?php if($u['profile_pic']): ?>
                        <img src="../uploads/<?= htmlspecialchars($u['profile_pic']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                    <?php endif; ?>
                    <?= htmlspecialchars($u['username']) ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge <?= $u['role']==='admin' ? 'badge-gold' : ($u['role']==='seller' ? 'badge-blue' : '') ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                    <?php if($u['role']==='seller'): ?>
                        <?= getBadgeHtml($pdo, $u['seller_tier'] ?: 'basic') ?>
                    <?php else: ?>—<?php endif; ?>
                </td>

                <td style="font-size:0.78rem; max-width:140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($u['faculty'] ?? '') ?>"><?= htmlspecialchars($u['faculty'] ?? '—') ?></td>
                <td>₵<?= number_format($u['balance'], 2) ?></td>
                <td>
                    <?php 
                        $is_suspended = !empty($u['suspended']);
                        if($is_suspended): ?>
                            <span class="badge badge-rejected">⛔ Suspended</span>
                        <?php elseif($u['verified']): ?>
                            <span style="color:var(--success);">✓ Verified</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                </td>
                <td style="font-size:0.8rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if($u['role'] !== 'admin'): ?>
                    <div class="flex gap-1" style="flex-wrap:wrap;">
                        <?php if(!$u['verified']): ?><a href="?action=verify&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm">Verify</a><?php endif; ?>
                        <?php if($u['role']==='seller'): ?>
                            <?php if($u['seller_tier']==='basic'): ?>
                                <a href="?action=upgrade_pro&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">🥈 Pro</a>
                                <a href="?action=upgrade_premium&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-gold btn-sm">⭐ Premium</a>
                            <?php elseif($u['seller_tier']==='pro'): ?>
                                <a href="?action=upgrade_premium&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-gold btn-sm">⭐ Premium</a>
                                <a href="?action=downgrade_basic&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">D-grade</a>
                            <?php elseif($u['seller_tier']==='premium'): ?>
                                <a href="?action=downgrade_basic&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">D-grade</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if(!empty($u['suspended'])): ?>
                            <a href="?action=reactivate&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm" onclick="return confirm('Reactivate this user?')">✅ Reactivate</a>
                        <?php else: ?>
                            <a href="?action=suspend&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm" style="color:#ff9500; border-color:rgba(255,149,0,0.3);" onclick="return confirm('Suspend this user? They will not be able to log in.')">⏸ Suspend</a>
                        <?php endif; ?>

                        <a href="../chat.php?user=<?= $u['id'] ?>" class="btn btn-outline btn-sm">💬 Message</a>
                        <a href="?action=delete&id=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user permanently?')">Delete</a>
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
