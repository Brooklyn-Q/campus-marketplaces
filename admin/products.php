<?php
$page_title = 'Moderation Desk';

// Must handle POST redirects BEFORE header.php outputs HTML
// header.php already checks isAdmin() and redirects non-admins
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

// --- Safe deletion helper ---
function deleteProductWithFiles(PDO $pdo, int $id): void
{
    $trashDir = '../uploads/temp_trash/';
    $uploadsDir = '../uploads/';

    if (!is_dir($trashDir)) {
        mkdir($trashDir, 0755, true);
    }

    $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();

    $movedFiles = [];
    foreach ($images as $img) {
        if (str_starts_with($img['image_path'], 'http')) {
            continue;
        }
        $safeName = basename($img['image_path']);
        $src = $uploadsDir . $safeName;
        $dst = $trashDir . $safeName;

        if (file_exists($src) && rename($src, $dst)) {
            $movedFiles[] = ['src' => $src, 'dst' => $dst];
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products        WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($movedFiles as $f) {
            if (file_exists($f['dst'])) {
                rename($f['dst'], $f['src']);
            }
        }
        throw $e;
    }

    foreach ($movedFiles as $f) {
        if (file_exists($f['dst'])) {
            unlink($f['dst']);
        }
    }
}

// Handle POST state-changing actions (BEFORE any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    check_csrf();

    $id = (int) $_POST['id'];
    $act = $_POST['action'];
    $valid = ['approve', 'reject', 'delete_approve', 'delete_cancel', 'force_delete'];

    if (in_array($act, $valid, true)) {
        try {
            switch ($act) {
                case 'approve':
                    $pdo->prepare("UPDATE products SET status='approved' WHERE id=?")->execute([$id]);
                    $_SESSION['flash_msg'] = "Product #$id approved.";
                    auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
                    break;

                case 'reject':
                    $pdo->prepare("UPDATE products SET status='rejected' WHERE id=?")->execute([$id]);
                    $_SESSION['flash_msg'] = "Product #$id rejected.";
                    auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
                    break;

                case 'delete_approve':
                    $pdo->beginTransaction();
                    try {
                        auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    deleteProductWithFiles($pdo, $id);
                    $_SESSION['flash_msg'] = "Product #$id and all associated files permanently deleted.";
                    break;

                case 'delete_cancel':
                    $pdo->prepare("UPDATE products SET status='pending' WHERE id=?")->execute([$id]);
                    $_SESSION['flash_msg'] = "Deletion cancelled for Product #$id — returned to pending review.";
                    auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
                    break;

                case 'force_delete':
                    $pdo->beginTransaction();
                    try {
                        auditLog($pdo, $_SESSION['user_id'], "Product $act #$id", 'product', $id);
                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    deleteProductWithFiles($pdo, $id);
                    $_SESSION['flash_msg'] = "Product #$id and all associated files permanently removed.";
                    break;
            }

        } catch (Throwable $e) {
            $_SESSION['flash_msg'] = "Error processing action on Product #$id. Please try again.";
            error_log("Moderation action '$act' on product #$id failed: " . $e->getMessage());
        }
    }

    $redirect_filter = $_POST['filter'] ?? 'all';
    header("Location: products.php?filter=" . urlencode($redirect_filter));
    exit;
}

// Now safe to output HTML
require_once 'header.php';

// Whitelist the filter parameter
$allowed_filters = ['all', 'pending', 'deletion_requested', 'approved', 'rejected'];
$filter = in_array($_GET['filter'] ?? '', $allowed_filters, true) ? $_GET['filter'] : 'all';

$query = "SELECT p.*, u.username AS seller, u.seller_tier,
          (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS thumb
          FROM products p JOIN users u ON p.user_id = u.id";
$params = [];
if ($filter !== 'all') {
    $query .= " WHERE p.status = ?";
    $params[] = $filter;
}
$query .= " ORDER BY CASE p.status
                WHEN 'pending'            THEN 1
                WHEN 'deletion_requested' THEN 2
                WHEN 'approved'           THEN 3
                WHEN 'rejected'           THEN 4
                WHEN 'paused'             THEN 5
                ELSE 6
            END ASC, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$csrf_token = $_SESSION['csrf_token'];
$filter_encoded = htmlspecialchars($filter);
?>

<h2 class="mb-2">Moderation Desk</h2>

<?php if (!empty($_SESSION['flash_msg'])): ?>
    <div class="alert alert-success fade-in"><?= htmlspecialchars($_SESSION['flash_msg']) ?></div>
    <?php unset($_SESSION['flash_msg']); ?>
<?php endif; ?>

<div class="flex gap-1 mb-3" style="flex-wrap:wrap;">
    <a href="?filter=all"
        class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-outline' ?> btn-sm"
        style="<?= $filter === 'pending' ? '' : 'border-color:var(--warning);color:var(--warning);' ?>">⏳
        Pending</a>
    <a href="?filter=deletion_requested"
        class="btn <?= $filter === 'deletion_requested' ? 'btn-danger' : 'btn-outline' ?> btn-sm"
        style="<?= $filter === 'deletion_requested' ? '' : 'border-color:var(--danger);color:var(--danger);' ?>">🗑️
        Deletion Requests</a>
    <a href="?filter=approved"
        class="btn <?= $filter === 'approved' ? 'btn-success' : 'btn-outline' ?> btn-sm">Approved</a>
    <a href="?filter=rejected"
        class="btn <?= $filter === 'rejected' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Rejected</a>
</div>

<div class="glass fade-in" style="padding:1.5rem; overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th></th>
                <th>ID</th>
                <th>Title</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['thumb']): ?>
                            <img src="<?= htmlspecialchars(
                                str_starts_with($p['thumb'], 'http')
                                ? $p['thumb']
                                : '../uploads/' . $p['thumb']
                            ) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" alt="">
                        <?php else: ?>
                            <div style="width:40px;height:40px;background:rgba(0,0,0,0.3);border-radius:6px;"></div>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $p['id'] ?></td>
                    <td>
                        <a href="../product.php?id=<?= (int) $p['id'] ?>" target="_blank"
                            style="color:#fff; font-weight:500;"><?= htmlspecialchars($p['title']) ?></a>
                    </td>
                    <td>
                        <?= htmlspecialchars($p['seller']) ?>
                        <?php if ($p['seller_tier'] === 'premium'): ?>
                            <span class="badge badge-gold" style="margin-left:2px;">⭐</span>
                        <?php endif; ?>
                    </td>
                    <td>₵<?= number_format((float) $p['price'], 2) ?></td>
                    <td><?= (int) $p['quantity'] ?></td>
                    <td>
                        <?php
                        $bc = match ($p['status']) {
                            'pending' => 'badge-pending',
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            'deletion_requested' => 'badge-deletion',
                            'paused' => 'badge-pending',
                            default => ''
                        };
                        ?>
                        <span
                            class="badge <?= $bc ?>"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $p['status']))) ?></span>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                            <button class="btn btn-success btn-sm"
                                onclick="submitAction('approve', <?= (int) $p['id'] ?>, 'Approve Product #<?= (int) $p['id'] ?>?')">
                                Approve
                            </button>
                            <button class="btn btn-outline btn-sm"
                                onclick="submitAction('reject', <?= (int) $p['id'] ?>, 'Reject Product #<?= (int) $p['id'] ?>?')">
                                Reject
                            </button>
                        <?php elseif ($p['status'] === 'deletion_requested'): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="submitAction('delete_approve', <?= (int) $p['id'] ?>, 'Permanently delete Product #<?= (int) $p['id'] ?> and all its files?\nThis cannot be undone.')">
                                Confirm Delete
                            </button>
                            <button class="btn btn-outline btn-sm"
                                onclick="submitAction('delete_cancel', <?= (int) $p['id'] ?>, 'Cancel deletion and return Product #<?= (int) $p['id'] ?> to pending review?')">
                                Cancel
                            </button>
                        <?php else: ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="submitAction('force_delete', <?= (int) $p['id'] ?>, 'Force delete Product #<?= (int) $p['id'] ?> and all its files?\nThis cannot be undone.')">
                                Force Delete
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Single shared form — populated and submitted by submitAction() -->
<form id="modActionForm" method="POST" style="display:none;">
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="id" id="formId">
    <input type="hidden" name="filter" value="<?= $filter_encoded ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
</form>

<script>
    function submitAction(action, id, confirmMsg) {
        if (!confirm(confirmMsg)) return;
        document.getElementById('formAction').value = action;
        document.getElementById('formId').value = id;
        document.getElementById('modActionForm').submit();
    }
</script>

<?php require_once 'footer.php'; ?>