<?php
$page_title = 'Moderation Desk';
require_once 'header.php';

$msg = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $act = $_GET['action'];
    $valid = ['approve','reject','delete_approve','delete_cancel','force_delete'];
    if (in_array($act, $valid)) {
        switch ($act) {
            case 'approve':
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=?")->execute([$id]);
                $msg = "Product #$id approved."; break;
            case 'reject':
                $pdo->prepare("UPDATE products SET status='rejected' WHERE id=?")->execute([$id]);
                $msg = "Product #$id rejected."; break;
            case 'delete_approve':
                $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
                $msg = "Product #$id permanently deleted. ID is now available for reuse.";
                break;
            case 'delete_cancel':
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=?")->execute([$id]);
                $msg = "Deletion cancelled for Product #$id."; break;
            case 'force_delete':
                $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
                $msg = "Product #$id permanently removed from active shop.";
                break;
        }
        auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
    }
}

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT p.*, u.username as seller, u.seller_tier,
          (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as thumb
          FROM products p JOIN users u ON p.user_id = u.id";
$params = [];
if ($filter !== 'all') { $query .= " WHERE p.status = ?"; $params[] = $filter; }
$order_sql = "CASE p.status WHEN 'pending' THEN 1 WHEN 'deletion_requested' THEN 2 WHEN 'approved' THEN 3 WHEN 'rejected' THEN 4 WHEN 'paused' THEN 5 ELSE 6 END ASC";
$query .= " ORDER BY $order_sql, p.created_at DESC";
$stmt = $pdo->prepare($query); $stmt->execute($params); $products = $stmt->fetchAll();
?>

<h2 class="mb-2">Moderation Desk</h2>
<?php if($msg): ?><div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="flex gap-1 mb-3" style="flex-wrap:wrap;">
    <a href="?filter=all" class="btn <?= $filter==='all'?'btn-primary':'btn-outline' ?> btn-sm">All</a>
    <a href="?filter=pending" class="btn <?= $filter==='pending'?'btn-primary':'btn-outline' ?> btn-sm" style="<?= $filter==='pending'?'':'border-color:var(--warning);color:var(--warning);' ?>">⏳ Pending</a>
    <a href="?filter=deletion_requested" class="btn <?= $filter==='deletion_requested'?'btn-danger':'btn-outline' ?> btn-sm" style="<?= $filter==='deletion_requested'?'':'border-color:var(--danger);color:var(--danger);' ?>">🗑️ Deletion Requests</a>
    <a href="?filter=approved" class="btn <?= $filter==='approved'?'btn-success':'btn-outline' ?> btn-sm">Approved</a>
    <a href="?filter=rejected" class="btn <?= $filter==='rejected'?'btn-primary':'btn-outline' ?> btn-sm">Rejected</a>
</div>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto;">
    <table>
        <thead><tr><th></th><th>ID</th><th>Title</th><th>Seller</th><th>Price</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach($products as $p): ?>
            <tr>
                <td>
                        <?php
                            $thumbSrc = str_starts_with($p['thumb'], 'http') ? $p['thumb'] : "../uploads/" . htmlspecialchars($p['thumb']);
                        ?>
                        <img src="<?= $thumbSrc ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                    <?php else: ?>
                        <div style="width:40px;height:40px;background:rgba(0,0,0,0.3);border-radius:6px;"></div>
                    <?php endif; ?>
                </td>
                <td><?= $p['id'] ?></td>
                <td><a href="../product.php?id=<?= $p['id'] ?>" target="_blank" style="color:#fff; font-weight:500;"><?= htmlspecialchars($p['title']) ?></a></td>
                <td>
                    <?= htmlspecialchars($p['seller']) ?>
                    <?php if($p['seller_tier']==='premium'): ?><span class="badge badge-gold" style="margin-left:2px;">⭐</span><?php endif; ?>
                </td>
                <td>₵<?= number_format($p['price'], 2) ?></td>
                <td><?= $p['quantity'] ?></td>
                <td>
                    <?php
                        $bc = match($p['status']) { 'pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected','deletion_requested'=>'badge-deletion','paused'=>'badge-pending',default=>'' };
                    ?>
                    <span class="badge <?= $bc ?>"><?= strtoupper(str_replace('_', ' ', $p['status'])) ?></span>
                </td>
                <td>
                    <div class="flex gap-1" style="flex-wrap:wrap;">
                        <?php if($p['status']==='pending'): ?>
                            <a href="?action=approve&id=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm">Approve</a>
                            <a href="?action=reject&id=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">Reject</a>
                        <?php elseif($p['status']==='deletion_requested'): ?>
                            <a href="?action=delete_approve&id=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn btn-danger btn-sm">Confirm Delete</a>
                            <a href="?action=delete_cancel&id=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline btn-sm">Cancel</a>
                        <?php else: ?>
                            <a href="?action=force_delete&id=<?= $p['id'] ?>&filter=<?= $filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('Force delete?')">Force Delete</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
