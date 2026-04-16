<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

if (!isset($_GET['id'])) redirect('dashboard.php');
$pid = (int)$_GET['id'];

// Get target product
$stmt = $pdo->prepare("SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image FROM products p WHERE p.id = ? AND p.user_id = ?");
$stmt->execute([$pid, $_SESSION['user_id']]);
$product = $stmt->fetch();

if (!$product) redirect('dashboard.php');

$imgUrl = $product['main_image'] ? 'uploads/' . $product['main_image'] : 'https://placehold.co/600x600?text=No+Image';

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in text-center" style="max-width:500px;">
    <h2 class="mb-2">📸 Promotional Flyer</h2>
    <p class="text-muted mb-3">Download this flyer to share on WhatsApp, Instagram, or Twitter!</p>

    <!-- Canvas area we will convert to image -->
    <div id="flyerCanvas" style="background:#0f172a; width:400px; height:400px; margin:0 auto; position:relative; overflow:hidden; border-radius:12px; border:2px solid var(--primary);">
        <img src="<?= htmlspecialchars($imgUrl) ?>" crossorigin="anonymous" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:1; opacity:0.8;">
        
        <!-- Gradient overlay -->
        <div style="position:absolute; bottom:0; left:0; width:100%; height:60%; background:linear-gradient(transparent, rgba(15,23,42,0.95)); z-index:2;"></div>

        <!-- Text overlay -->
        <div style="position:absolute; bottom:20px; left:20px; right:20px; z-index:3; text-align:left;">
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                    <span style="background:var(--primary); color:#fff; padding:3px 8px; border-radius:4px; font-size:0.7rem; font-weight:700; display:inline-block; margin-bottom:5px;">CAMPUS MARKETPLACE</span>
                    <h3 style="color:#fff; font-size:1.3rem; margin:0; line-height:1.2; font-weight:800; text-shadow:0 2px 4px rgba(0,0,0,0.8);"><?= htmlspecialchars($product['title']) ?></h3>
                </div>
                <div style="background:var(--gold); color:#000; padding:5px 10px; border-radius:8px; font-weight:800; font-size:1.4rem; box-shadow:0 4px 10px rgba(0,0,0,0.5);">
                    ₵<?= number_format($product['price'], 2) ?>
                </div>
            </div>
            <p style="color:#cbd5e1; font-size:0.75rem; margin-top:8px; line-height:1.4; text-shadow:0 1px 2px rgba(0,0,0,0.8);">
                <?= htmlspecialchars(substr($product['description'], 0, 80)) ?>...
            </p>
        </div>
    </div>

    <!-- html2canvas library to generate actual image -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <div class="mt-3 flex gap-1" style="justify-content:center;">
        <button onclick="downloadFlyer()" class="btn btn-primary">⬇️ Download Flyer</button>
        <a href="dashboard.php" class="btn btn-outline">Back</a>
    </div>

    <script>
    function downloadFlyer() {
        const tgt = document.getElementById('flyerCanvas');
        html2canvas(tgt, { useCORS: true, backgroundColor: null }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Flyer-<?= $product['id'] ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }
    </script>
</div>

<?php require_once 'includes/footer.php'; ?>
