<?php
$page_title = 'Ad Manager';
require_once 'header.php';
require_once '../includes/storage_helper.php';

// Create ads table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_placements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        image_path VARCHAR(2048) DEFAULT '',
        link_url VARCHAR(2048) DEFAULT '#',
        placement ENUM('homepage','category','product') DEFAULT 'homepage',
        is_active TINYINT(1) DEFAULT 1,
        impressions INT DEFAULT 0,
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
} catch (Exception $e) {
}

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (isset($_POST['create_ad'])) {
        $title = trim($_POST['ad_title'] ?? '');
        $image_url = trim($_POST['ad_image'] ?? '');
        if ($image_url && !preg_match('#^https?://#i', $image_url)) {
            $msg = "❌ Image URL must start with http:// or https://";
            $title = ''; // Prevent saving
        }
        $link = trim($_POST['ad_link'] ?? '#');
        $placement = $_POST['ad_placement'] ?? 'homepage';

        // Handle File Upload if provided
        if (!empty($_FILES['ad_file']['name']) && $_FILES['ad_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['ad_file']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            // Validate MIME type natively to ensure it's truly an image
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['ad_file']['tmp_name']);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (in_array($ext, $allowedExts) && in_array($mimeType, $allowedMimes)) {
                $fname = 'ad_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $storedPath = storage_upload($_FILES['ad_file']['tmp_name'], 'ads', $fname, $mimeType);
                if ($storedPath) {
                    $image_url = $storedPath; // Store cloud URL
                } else {
                    $msg = "❌ Failed to upload image.";
                }
            } else {
                $msg = "❌ Invalid image format or corrupted file.";
            }
        }

        if ($title && !$msg) {
            $safe_url = $image_url;
            $pdo->prepare("INSERT INTO ad_placements (title, image_path, link_url, placement) VALUES (?,?,?,?)")
                ->execute([$title, $safe_url, $link, $placement]);
            $msg = "✅ Ad created successfully!";
        }
    }
}

// CSRF check added for GET actions
if (isset($_GET['action'])) {
    if (empty($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die("❌ Security check failed. Please refresh and try again.");
    }

    $adId = (int) ($_GET['id'] ?? 0);
    if ($_GET['action'] === 'toggle') {
        $pdo->prepare("UPDATE ad_placements SET is_active = NOT is_active WHERE id = ?")->execute([$adId]);
        $msg = "✅ Ad toggled.";
    } elseif ($_GET['action'] === 'delete') {
        // Option to delete physical file could be added here
        $pdo->prepare("DELETE FROM ad_placements WHERE id = ?")->execute([$adId]);
        $msg = "✅ Ad deleted.";
    }
}

$ads = $pdo->query("SELECT * FROM ad_placements ORDER BY created_at DESC")->fetchAll();
?>

<h2 class="mb-3" style="display:flex; align-items:center; gap:0.5rem;">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5"
        stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
        <line x1="9" y1="3" x2="9" y2="21" />
    </svg>
    Ad Manager
</h2>

<?php if ($msg): ?>
    <div class="alert alert-success fade-in"><?= $msg ?></div><?php endif; ?>

<div class="glass fade-in mb-3" style="padding:1.5rem;">
    <h4 class="mb-2">📢 Create New Ad Placement</h4>
    <form method="POST" enctype="multipart/form-data" class="ad-form-grid">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Ad Title *</label>
            <input type="text" name="ad_title" class="form-control" required placeholder="e.g. Back to School Sale">
        </div>
        <div class="form-group">
            <label>Upload Image</label>
            <input type="file" name="ad_file" class="form-control">
        </div>
        <div class="form-group">
            <label>Or Image URL</label>
            <input type="url" name="ad_image" class="form-control" placeholder="https://...">
        </div>
        <div class="form-group">
            <label>Link URL</label>
            <input type="url" name="ad_link" class="form-control" placeholder="https://..." value="#">
        </div>
        <div class="form-group">
            <label>Placement</label>
            <select name="ad_placement" class="form-control">
                <option value="homepage">Homepage</option>
                <option value="category">Category Page</option>
                <option value="product">Product Page</option>
            </select>
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit" name="create_ad" class="btn btn-primary">Create Ad</button>
        </div>
    </form>
</div>

<div class="glass fade-in" style="padding:1.5rem;">
    <h4 class="mb-2">📋 All Ad Placements</h4>
    <?php if (count($ads) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Placement</th>
                    <th>Status</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td style="font-weight:600;">
                            <div class="flex gap-1" style="align-items:center;">
                                <?php if ($ad['image_path']): ?>
                                    <?php
                                    $src = (strpos($ad['image_path'], 'http') === 0) ? $ad['image_path'] : '../' . $ad['image_path'];
                                    ?>
                                    <img src="<?= htmlspecialchars($src) ?>"
                                        style="width:40px; height:30px; border-radius:4px; object-fit:cover; border:1px solid rgba(0,0,0,0.1);">
                                <?php endif; ?>
                                <?= htmlspecialchars($ad['title']) ?>
                            </div>
                        </td>
                        <td><span class="badge badge-blue" style="font-size:0.65rem;"><?= ucfirst($ad['placement']) ?></span>
                        </td>
                        <td>
                            <?php if ($ad['is_active']): ?>
                                <span class="badge badge-approved" style="font-size:0.65rem;">Active</span>
                            <?php else: ?>
                                <span class="badge badge-rejected" style="font-size:0.65rem;">Paused</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($ad['impressions']) ?></td>
                        <td><?= number_format($ad['clicks']) ?></td>
                        <td><?= $ad['impressions'] > 0 ? round($ad['clicks'] / $ad['impressions'] * 100, 1) . '%' : '0%' ?></td>
                        <td style="display:flex; gap:0.3rem;">
                            <a href="?action=toggle&id=<?= $ad['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                class="btn btn-sm btn-outline"><?= $ad['is_active'] ? 'Pause' : 'Activate' ?></a>
                            <a href="?action=delete&id=<?= $ad['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                class="btn btn-sm btn-danger" onclick="return confirm('Delete this ad?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted" style="text-align:center; padding:1.5rem;">No ad placements yet. Create your first one above.
        </p>
    <?php endif; ?>
</div>

<style>
    /* Replaced brittle inline selector with a robust class */
    .ad-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    @media(max-width:600px) {
        .ad-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once 'footer.php'; ?>