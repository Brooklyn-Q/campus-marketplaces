<?php
require_once 'includes/db.php';
require_once 'includes/storage_helper.php';
if (!isLoggedIn()) redirect('login.php');
if (!isSeller() && !isAdmin()) redirect('dashboard.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!canAddProduct($pdo, $user['id'])) {
    $tier = $user['seller_tier'] ?: 'basic';
    $limit = (int)getSetting($pdo, "{$tier}_product_limit", 3);
    $_SESSION['flash'] = "Your {$tier} tier limit of {$limit} products has been reached. Please upgrade to unlock more.";
    redirect('dashboard.php');
}


$maxImages = getMaxImages($pdo, $user['id']);
$error = ''; $success = '';
$categories = ['Computer & Accessories','Phone & Accessories','Electrical Appliances','Fashion','Food & Groceries','Education & Books','Hostels for Rent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category    = $_POST['category'] ?? 'General';
    $quantity    = max(1, (int)($_POST['quantity'] ?? 1));
    $promo_tag   = $_POST['promo_tag'] ?? '';
    $delivery_method = $_POST['delivery_method'] ?? 'Pickup';
    $payment_agreement = $_POST['payment_agreement'] ?? 'Pay on delivery';

    // Ensure promo_tag column exists
    try { $pdo->exec("ALTER TABLE products ADD COLUMN promo_tag VARCHAR(50) DEFAULT '' AFTER quantity"); } catch(Exception $e) {}

    if (empty($title) || empty($description)) { $error = "Title and description are required."; }
    elseif ($price === false || $price <= 0)   { $error = "Enter a valid price."; }
    else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO products (user_id, title, description, price, category, quantity, promo_tag, delivery_method, payment_agreement, status) VALUES (?,?,?,?,?,?,?,?,?,'pending')");
            $stmt->execute([$user['id'], $title, $description, $price, $category, $quantity, $promo_tag, $delivery_method, $payment_agreement]);
            $productId = (int)$pdo->lastInsertId();

            // Handle multiple image uploads
            if (!is_dir(__DIR__ . '/uploads/products')) mkdir(__DIR__ . '/uploads/products', 0755, true);
            $allowed = ['jpg','jpeg','png','webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 50 * 1024 * 1024; // 50MB
            $imgCount = 0;
            if (isset($_FILES['images'])) {
                foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                    if ($imgCount >= $maxImages) break;
                    if ($_FILES['images']['error'][$i] !== 0) continue;

                    // SECURITY: Check file extension
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;

                    // SECURITY: Check file size
                    if ($_FILES['images']['size'][$i] > $maxFileSize) continue;

                    // SECURITY: Validate MIME type with finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $tmp);
                    if (!in_array($mimeType, $allowedMimes)) continue;

                    // SECURITY: Strip EXIF data and re-encode image
                    $image = null;
                    $stripExif = false;
                    if ($mimeType === 'image/jpeg') {
                        $image = @imagecreatefromjpeg($tmp);
                        $stripExif = true;
                    } elseif ($mimeType === 'image/png') {
                        $image = @imagecreatefrompng($tmp);
                    } elseif ($mimeType === 'image/webp') {
                        $image = @imagecreatefromwebp($tmp);
                    }

                    if (!$image) continue; // Invalid image

                    $fname = uniqid('p_', true) . '.' . $ext;
                    $tempReEncoded = sys_get_temp_dir() . '/' . $fname;

                    // Save re-encoded image (strips EXIF)
                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        imagejpeg($image, $tempReEncoded, 85);
                    } elseif ($ext === 'png') {
                        imagepng($image, $tempReEncoded, 8);
                    } elseif ($ext === 'webp') {
                        imagewebp($image, $tempReEncoded, 85);
                    }

                    $storedPath = storage_upload($tempReEncoded, 'products', $fname, $mimeType);

                    if ($storedPath) {
                        $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)")->execute([$productId, $storedPath, $imgCount]);
                        $imgCount++;
                    }
                    if (file_exists($tempReEncoded)) @unlink($tempReEncoded);
                }
            }


            // Update last upload timestamp
            $pdo->prepare("UPDATE users SET last_upload_at = NOW() WHERE id = ?")->execute([$user['id']]);

            $pdo->commit();
            $success = "Product submitted for review. It will appear once admin approves.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to submit product. Try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in" style="max-width:700px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem;">
        <div>
            <h2 class="mb-1">Add New Product</h2>
            <p class="text-muted" style="font-size:0.85rem;">Your product will be reviewed by an admin before appearing live.</p>
        </div>
        <div style="text-align:right;">
            <p class="text-muted" style="font-size:0.75rem; margin-bottom:4px;">Seller Identity</p>
            <?= getBadgeHtml($pdo, $user['seller_tier'] ?: 'basic') ?>
        </div>
    </div>

    <div class="glass" style="padding:1rem; margin-bottom:1.5rem; border-left:4px solid var(--primary); background:rgba(0,113,227,0.05);">
        <p style="font-size:0.85rem; font-weight:600;">Tier Benefits (<?= ucfirst($user['seller_tier'] ?: 'basic') ?>):</p>
        <ul style="font-size:0.8rem; margin:8px 0 0 16px; color:var(--text-muted);">
            <li>Max images per product: <strong><?= $maxImages ?></strong></li>
            <li>Visible to: <strong>All campus users</strong></li>
            <li>Verification status: <strong><?= $user['verified'] ? 'Verified' : 'Standard' ?></strong></li>
        </ul>
    </div>

    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <a href="dashboard.php" class="btn btn-primary" style="width:100%;justify-content:center;">Back to Dashboard</a>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data" id="addProductForm">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Product Title *</label>
            <input type="text" name="title" class="form-control" required maxlength="150" id="productTitle">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category *</label>
                <select name="category" class="form-control" required>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c ?>"><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Promo Tag <span style="color:var(--text-muted); font-weight:400; font-size:0.8rem;">(optional — makes your listing stand out)</span></label>
            <select name="promo_tag" class="form-control">
                <option value="">No promo tag</option>
                <option value="🔥 Hot Deal">🔥 Hot Deal</option>
                <option value="⚡ Flash Sale">⚡ Flash Sale</option>
                <option value="⏳ Limited Offer">⏳ Limited Offer</option>
                <option value="🎓 Student Special">🎓 Student Special</option>
                <option value="📦 Bundle Deal">📦 Bundle Deal</option>
                <option value="🏷️ Clearance">🏷️ Clearance</option>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Price (₵) *</label>
                <input type="number" step="0.01" name="price" class="form-control" required min="0.01" id="productPrice">
            </div>
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="quantity" class="form-control" required min="1" value="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Delivery Method *</label>
                <select name="delivery_method" class="form-control" required>
                    <option value="Pickup">Pickup</option>
                    <option value="Delivery">Delivery</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Agreement *</label>
                <select name="payment_agreement" class="form-control" required>
                    <option value="Pay on delivery">Pay on delivery</option>
                    <option value="Pay before delivery">Pay before delivery</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="flex-between mb-1">
                <label style="margin:0;">Description *</label>
                <button type="button" class="btn btn-primary btn-sm" onclick="generateAI()" id="aiBtn">✨ Generate AI Text</button>
            </div>
            <textarea name="description" class="form-control" rows="5" required id="productDesc"></textarea>
        </div>
        <div class="form-group">
            <label>Product Images (max <?= $maxImages ?>)</label>
            <input type="file" name="images[]" class="form-control" accept="image/*" multiple id="imgUpload">
            <small class="text-muted">Supported: JPG, PNG, WebP</small>
        </div>

        <!-- Live Preview -->
        <div class="glass" style="padding:1.5rem; margin:1.5rem 0;">
            <h4 class="mb-2">Live Preview</h4>
            <div style="display:flex; gap:1rem; align-items:flex-start;" id="livePreview">
                <div id="previewImgWrap" style="width:150px; height:150px; background:rgba(0,0,0,0.3); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#555; font-size:0.8rem; overflow:hidden; flex-shrink:0;">
                    <span id="previewPlaceholder">Image preview</span>
                    <img id="previewImg" src="" style="width:100%;height:100%;object-fit:cover;display:none;">
                </div>
                <div style="flex:1;">
                    <h3 id="previewTitle" style="color:#fff; margin-bottom:2px;">Product Title</h3>
                    <div style="margin-bottom:8px;">
                        <?= getBadgeHtml($pdo, $user['seller_tier'] ?: 'basic') ?>
                    </div>
                    <p id="previewPrice" style="color:var(--primary); font-weight:700; font-size:1.3rem;">₵0.00</p>
                    <p id="previewDesc" class="text-muted" style="font-size:0.85rem; margin-top:0.5rem;">Description will appear here...</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; font-size:1rem; padding:0.85rem;">Submit for Review</button>
    </form>

    <script>
    document.getElementById('productTitle')?.addEventListener('input', e => { document.getElementById('previewTitle').textContent = e.target.value || 'Product Title'; });
    document.getElementById('productPrice')?.addEventListener('input', e => { document.getElementById('previewPrice').textContent = '₵' + parseFloat(e.target.value || 0).toFixed(2); });
    document.getElementById('productDesc')?.addEventListener('input', e => { document.getElementById('previewDesc').textContent = e.target.value || 'Description...'; });
    document.getElementById('imgUpload')?.addEventListener('change', e => {
        if(e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = ev => { document.getElementById('previewImg').src = ev.target.result; document.getElementById('previewImg').style.display='block'; document.getElementById('previewPlaceholder').style.display='none'; };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    async function generateAI() {
        const title = document.getElementById('productTitle').value;
        if (!title) return alert('Please enter a Product Title first!');
        const btn = document.getElementById('aiBtn');
        const desc = document.getElementById('productDesc');
        btn.innerHTML = '⏳ Generating...'; btn.disabled = true;
        
        // Mock API Call delay
        setTimeout(() => {
            desc.value = `Selling my slightly used ${title}. Great condition, carefully maintained with no hidden faults. Perfect for students looking for a reliable deal at an affordable price. Comes from a clean environment. Feel free to message me for negotiations!`;
            document.getElementById('previewDesc').textContent = desc.value;
            btn.innerHTML = '✨ Generate AI Text'; btn.disabled = false;
        }, 1500);
    }
    </script>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
