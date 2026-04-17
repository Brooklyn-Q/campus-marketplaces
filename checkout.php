<?php
require_once 'includes/db.php';
if (!isLoggedIn()) {
    redirect('login.php?redirect=checkout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    check_csrf();
    $ids = explode(',', $_POST['item_ids'] ?? '');
    $ids = array_filter(array_map('intval', $ids));
    if (!empty($ids)) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id as pid, user_id as seller_id, price FROM products WHERE id IN ($in)");
        $stmt->execute($ids);
        $products = $stmt->fetchAll();
        $buyer_id = (int)$_SESSION['user_id'];
        $buyer_name = $_SESSION['username'];
        
        $pdo->beginTransaction();
        try {
            foreach ($products as $p) {
                if ($p['seller_id'] == $buyer_id) continue;
                
                $istmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, product_id, price) VALUES (?, ?, ?, ?)");
                $istmt->execute([$buyer_id, $p['seller_id'], $p['pid'], $p['price']]);
                $order_id = $pdo->lastInsertId();
                
                $msg = "This product has been ordered by " . $buyer_name;
                $nstmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'order_received', ?, ?)");
                $nstmt->execute([$p['seller_id'], $msg, $order_id]);
            }
            $pdo->commit();
            echo "<script>localStorage.removeItem('cm_cart'); alert('Orders placed successfully! The sellers have been notified.'); window.location.href='dashboard.php';</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}
require_once 'includes/header.php';
?>

<?php if (!isset($_GET['ids'])): ?>
    <script>
        (function() {
            try {
                const cartRaw = localStorage.getItem('cm_cart');
                const cart = cartRaw ? JSON.parse(cartRaw) : [];
                if (cart.length > 0) {
                    const ids = cart.map(i => i.id).join(',');
                    window.location.replace('checkout.php?ids=' + ids);
                } else {
                    window.location.replace('index.php');
                }
            } catch(e) {
                window.location.replace('index.php');
            }
        })();
    </script>
    <div style="text-align:center; padding: 5rem;"><p>Loading checkout...</p></div>
<?php else: ?>
    <?php
    $ids = explode(',', $_GET['ids']);
    $ids = array_filter(array_map('intval', $ids));
    
    if (empty($ids)) {
        echo '<div class="container text-center" style="padding:4rem;"><p>No items found.</p><a href="index.php" class="btn btn-primary">Go Back</a></div>';
    } else {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT p.id as pid, p.title, p.price, u.id as seller_id, u.username, u.phone, u.whatsapp FROM products p JOIN users u ON p.user_id = u.id WHERE p.id IN ($in)");
        $stmt->execute($ids);
        $items = $stmt->fetchAll();
        
        echo '<div class="container fade-in" style="max-width:800px; padding:4vh 5%;">';
        echo '<h1 class="mb-3" style="font-size:2rem; font-weight:800;">Complete Your Purchase</h1>';
        echo '<div class="liquid-glass-card" style="padding:2rem;">';
        echo '<p class="text-muted mb-3">To complete your purchase, please contact the sellers of the items directly to arrange payment and delivery.</p>';
        
        foreach($items as $i) {
            echo '<div style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(128,128,128,0.2);">';
            echo '<h3 style="font-size:1.2rem; font-weight:700;">' . htmlspecialchars($i['title']) . ' <span style="color:var(--primary);">₵' . number_format($i['price'],2) . '</span></h3>';
            echo '<p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.75rem;">Seller: <strong>' . htmlspecialchars($i['username']) . '</strong></p>';
            echo '<div class="flex gap-1 flex-wrap">';
            if (!empty($i['phone'])) echo '<a href="tel:' . htmlspecialchars($i['phone']) . '" class="btn btn-outline btn-sm">📞 Call Seller</a>';
            if (!empty($i['whatsapp'])) echo '<a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $i['whatsapp']) . '" target="_blank" class="btn btn-success btn-sm" style="border:none;">💬 WhatsApp</a>';
            if (isLoggedIn() && $_SESSION['user_id'] != $i['seller_id']) echo '<a href="chat.php?user=' . $i['seller_id'] . '" class="btn btn-primary btn-sm">✉️ Message in App</a>';
            echo '</div></div>';
        }
        
        echo '<form method="POST" style="text-align:center; margin-top:2rem;">';
        echo csrf_field();
        echo '<input type="hidden" name="item_ids" value="'.htmlspecialchars($_GET['ids']).'">';
        echo '<p style="color:var(--text-main); font-weight:600; font-size:1.1rem; margin-bottom:1rem;">Payment Method: Pay on Delivery Only</p>';
        echo '<button type="submit" name="place_order" class="btn btn-primary" onclick="cmCart.clear();">Mark as Ordered</button>';
        echo '</form>';
        echo '</div></div>';
    }
    ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
