<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf();
    $pid = (int)($_POST['pid'] ?? 0);
    $action = $_POST['action'];
    switch ($action) {
        case 'request_delete':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='deletion_requested' WHERE id=? AND user_id=? AND status NOT IN ('deletion_requested')")->execute([$pid, $user['id']]);
                $msg = "Deletion request submitted.";
            }
            break;
        case 'pause':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='paused' WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                $msg = "Product paused.";
            }
            break;
        case 'unpause':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=? AND user_id=? AND status='paused'")->execute([$pid, $user['id']]);
                $msg = "Product resumed.";
            }
            break;
        case 'mark_sold':
            if ($pid > 0) {
                $pdo->prepare("UPDATE products SET quantity=0, status='sold' WHERE id=? AND user_id=?")->execute([$pid, $user['id']]);
                $msg = "Product marked as sold out.";
            }
            break;
        case 'restock_qty':
            $qty = (int)($_POST['restock_amount'] ?? 0);
            $restock_pid = (int)($_POST['pid'] ?? 0);
            if ($qty > 0 && $restock_pid > 0) {
                // Update quantity
                $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id=? AND user_id=?")->execute([$qty, $restock_pid, $user['id']]);
                // If product was sold out, re-approve it
                $pdo->prepare("UPDATE products SET status='approved' WHERE id=? AND user_id=? AND status='sold'")->execute([$restock_pid, $user['id']]);
                $msg = "Product successfully restocked with +$qty items!";
            }
            break;
        case 'toggle_vacation':
            if ($user['vacation_mode']) {
                $pdo->prepare("UPDATE users SET vacation_mode=0 WHERE id=?")->execute([$user['id']]);
                $user['vacation_mode'] = 0;
                $msg = "Welcome back! Your listings are visible again.";
            } else {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS vacation_requests (
                        id SERIAL PRIMARY KEY,
                        seller_id INT NOT NULL,
                        status VARCHAR(20) DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                } catch(PDOException $e) {}
                $pdo->prepare("INSERT INTO vacation_requests (seller_id, status) VALUES (?, 'pending')")->execute([$user['id']]);
                $msg = "🏝️ Vacation request submitted for Admin approval. Your listings will be hidden once approved.";
            }
            break;

        case 'boost':
            if ($pid > 0) {
                try {
                    $pdo->beginTransaction();
                    $isPremium = ($user['seller_tier'] === 'premium');
                    $boostPrice = $isPremium ? 0.00 : (float)getSetting($pdo, 'ad_boost_price', '10');

                    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$user['id']]);
                    $u = $stmt->fetch();

                    if (!$u || $u['balance'] < $boostPrice) throw new Exception("Insufficient funds (₵$boostPrice required) to boost.");

                    if ($boostPrice > 0) {
                        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$boostPrice, $user['id']]);
                    }

                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                        $pdo->prepare("UPDATE products SET boosted_until = CURRENT_TIMESTAMP + INTERVAL '24 hours' WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                    } else {
                        $pdo->prepare("UPDATE products SET boosted_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id=? AND user_id=? AND status='approved'")->execute([$pid, $user['id']]);
                    }
                    $pdo->prepare("INSERT INTO transactions (user_id,type,amount,status,reference,description) VALUES (?,'boost',?,?,?,?)")->execute([$user['id'], $boostPrice, 'completed', generateRef('BST'), "Boosted Product #$pid" . ($isPremium ? " (Free Premium Benefit)" : "")]);

                    $pdo->commit();
                    $msg = $isPremium ? "⚡ Product Successfully Boosted (Free Premium Benefit)!" : "⚡ Product Successfully Boosted for 24 hours!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error: " . $e->getMessage();
                }
            }
            break;

        case 'request_premium':
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, 1, ?)")->execute([$user['id'], "Hello Admin! I am requesting a Platinum/Premium Badge upgrade for my seller account. I agree to pay the premium fee."]);
            $msg = "⭐ Premium request sent to Administrator!";
            break;
        case 'request_pro':
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, 1, ?)")->execute([$user['id'], "Hello Admin! I am requesting a Pro Badge upgrade for my seller account. I agree to pay the pro fee."]);
            $msg = "🥈 Pro request sent to Administrator!";
            break;
        case 'cancel_tier':
            // Check for existing pending request to avoid duplicates
            $check = $pdo->prepare("SELECT COUNT(*) FROM profile_edit_requests WHERE user_id=? AND field_name='seller_tier' AND status='pending'");
            $check->execute([$user['id']]);
            if($check->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?, 'seller_tier', ?, 'basic')")
                    ->execute([$user['id'], $user['seller_tier']]);
                $msg = "🛑 Tier cancellation request submitted for admin approval.";
            } else {
                $msg = "⚠️ You already have a pending cancellation request.";
            }
            break;

        // ADDED: Submit discount for admin approval
        case 'submit_discount':
            $discount_pct = (int)($_GET['disc'] ?? 0);
            if ($pid > 0 && $discount_pct > 0 && $discount_pct <= 90) {
                $prod_check = $pdo->prepare("SELECT id, title, price FROM products WHERE id = ? AND user_id = ?");
                $prod_check->execute([$pid, $user['id']]);
                $prod_data = $prod_check->fetch(PDO::FETCH_ASSOC);
                if ($prod_data) {
                    $discounted = $prod_data['price'] * (1 - $discount_pct / 100);
                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                            id SERIAL PRIMARY KEY, product_id INT NOT NULL, seller_id INT NOT NULL,
                            original_price DECIMAL(10,2) NOT NULL, discount_percent INT NOT NULL,
                            discounted_price DECIMAL(10,2) NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                    } else {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_requests (
                            id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, seller_id INT NOT NULL,
                            original_price DECIMAL(10,2) NOT NULL, discount_percent INT NOT NULL,
                            discounted_price DECIMAL(10,2) NOT NULL,
                            status ENUM('pending','approved','rejected') DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB");
                    }
                    $pdo->prepare("INSERT INTO discount_requests (product_id, seller_id, original_price, discount_percent, discounted_price) VALUES (?,?,?,?,?)")
                        ->execute([$pid, $user['id'], $prod_data['price'], $discount_pct, $discounted]);
                    $msg = "📨 Discount request ({$discount_pct}% off \"{$prod_data['title']}\") submitted for admin approval!";
                }
            } else {
                $msg = "⚠️ Enter a valid discount (1-90%).";
            }
            break;
        case 'accept_order':
            $oid = (int)($_GET['oid'] ?? 0);
            $note = trim($_POST['delivery_note'] ?? '');
            if ($oid > 0 && $note) {
                // Seller accepts
                $pdo->prepare("UPDATE orders SET status='seller_seen', delivery_note=? WHERE id=? AND seller_id=?")->execute([$note, $oid, $user['id']]);
                // Notify Buyer
                $o = $pdo->prepare("SELECT buyer_id, p.title FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=?"); $o->execute([$oid]); $ord = $o->fetch();
                if ($ord) {
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'order_update', ?, ?)")
                        ->execute([$ord['buyer_id'], "Order seen by seller. Delivery update: " . $note, $oid]);
                }
                $msg = "Order accepted and buyer notified.";
            }
            break;
        case 'deliver_order':
            $oid = (int)($_GET['oid'] ?? 0);
            if ($oid > 0) {
                $boolT = sqlBool(true, $pdo);
                $status_sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
                    ? "CASE WHEN buyer_confirmed = $boolT THEN 'completed' ELSE 'delivered' END"
                    : "IF(buyer_confirmed = 1, 'completed', 'delivered')";
                $pdo->prepare("UPDATE orders SET seller_confirmed=$boolT, status=$status_sql WHERE id=? AND seller_id=?")->execute([$oid, $user['id']]);
                // Notify admin/buyer
                $o = $pdo->prepare("SELECT buyer_id, p.id as pid FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=?"); $o->execute([$oid]); $ord = $o->fetch();
                if ($ord) {
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'order_update', ?, ?)")->execute([$ord['buyer_id'], "Seller confirmed Item Sold. Please confirm when received.", $oid]);
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'admin_alert', ?, ?)")->execute([1, "Seller confirmed order #$oid as Item Sold.", $oid]);
                }
                $msg = "Item Sold confirmed successfully.";
            }
            break;
        case 'receive_order':
            $oid = (int)($_GET['oid'] ?? 0);
            if ($oid > 0) {
                $boolT = sqlBool(true, $pdo);
                $status_sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
                    ? "CASE WHEN seller_confirmed = $boolT THEN 'completed' ELSE 'delivered' END"
                    : "IF(seller_confirmed = 1, 'completed', 'delivered')";
                $pdo->prepare("UPDATE orders SET buyer_confirmed=$boolT, status=$status_sql WHERE id=? AND buyer_id=?")->execute([$oid, $user['id']]);
                $o = $pdo->prepare("SELECT seller_id, p.id as pid FROM orders o JOIN products p ON o.product_id=p.id WHERE o.id=?"); $o->execute([$oid]); $ord = $o->fetch();
                if ($ord) {
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'order_update', ?, ?)")->execute([$ord['seller_id'], "Buyer confirmed Item Received. Transaction complete.", $oid]);
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'admin_alert', ?, ?)")->execute([1, "Buyer confirmed receipt of order #$oid. Transaction complete.", $oid]);
                }
                $msg = "Item Received confirmed. Transaction complete.";
            }
            break;
        case 'submit_dispute':
            $oid = (int)($_GET['oid'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if($oid > 0 && $reason) {
                $o = $pdo->prepare("SELECT seller_id FROM orders WHERE id=? AND buyer_id=?");
                $o->execute([$oid, $user['id']]);
                $ord = $o->fetch();
                if($ord) {
                    $pdo->prepare("INSERT INTO disputes (order_id, complainant_id, target_id, reason) VALUES (?,?,?,?)")
                        ->execute([$oid, $user['id'], $ord['seller_id'], $reason]);
                    $msg = "🚨 Dispute reported successfully. Admin will review this shortly.";
                }
            }
            break;
    }
}

// Re-fetch user after potential changes
$user = getUser($pdo, $user['id']);

// Stats
try {
    $productCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?"); 
    $productCount->execute([$user['id']]); 
    $totalProducts = $productCount->fetchColumn();

    $approvedCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status='approved'"); 
    $approvedCount->execute([$user['id']]); 
    $totalApproved = $approvedCount->fetchColumn();

    $pendingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status='pending'");
    $pendingCountStmt->execute([$user['id']]);
    $totalPending = $pendingCountStmt->fetchColumn();

    $totalSales = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type='sale'"); 
    $totalSales->execute([$user['id']]); 
    $salesAmount = $totalSales->fetchColumn();

    $totalViews = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM products WHERE user_id = ?"); 
    $totalViews->execute([$user['id']]); 
    $viewsTotal = $totalViews->fetchColumn();

    // Show ALL seller products
    $stmt = $pdo->prepare("SELECT p.*, 
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
        (SELECT COUNT(*) FROM discount_requests WHERE product_id = p.id AND status = 'pending') as has_pending_discount
        FROM products p WHERE p.user_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$user['id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('dashboard.php DB error: ' . $e->getMessage());
    $msg = "Temporary database issue. Please refresh in a moment.";
    $products = [];
    $totalProducts = 0;
    $totalApproved = 0;
    $totalPending = 0;
    $salesAmount = 0;
    $viewsTotal = 0;
}

// Transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Announcements
$announcement_active = sqlBool(true, $pdo);
$stmt = $pdo->prepare("SELECT a.*, u.username as admin_name FROM announcements a JOIN users u ON a.admin_id = u.id WHERE a.is_active = $announcement_active ORDER BY a.created_at DESC LIMIT 5");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// ═══ REAL ANALYTICS DATA ═══

// --- BUYER ANALYTICS ---
$buyerItemsBought = 0; $buyerTotalSpent = 0; $buyerRecentPurchases = []; $buyerFavCategories = [];
if ($user['role'] === 'buyer') {
    try {
        // Total items bought (from orders table)
        $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status='completed'"); $s->execute([$user['id']]); $buyerItemsBought = (int)$s->fetchColumn();
    } catch(PDOException $e) { /* orders table may not exist */ }
    try {
        // Total money spent
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type IN ('purchase','order')"); $s->execute([$user['id']]); $buyerTotalSpent = (float)$s->fetchColumn();
    } catch(PDOException $e) {}
    try {
        // Recent purchases
        $s = $pdo->prepare("SELECT o.*, p.title, (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) as img FROM orders o JOIN products p ON o.product_id=p.id WHERE o.buyer_id=? ORDER BY o.created_at DESC LIMIT 5");
        $s->execute([$user['id']]); $buyerRecentPurchases = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(PDOException $e) {}
    try {
        // Favorite categories
        $s = $pdo->prepare("SELECT p.category, COUNT(*) as cnt FROM orders o JOIN products p ON o.product_id=p.id WHERE o.buyer_id=? GROUP BY p.category ORDER BY cnt DESC LIMIT 4");
        $s->execute([$user['id']]); $buyerFavCategories = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(PDOException $e) {}
}

// --- SELLER ANALYTICS ---
$sellerTotalSold = 0; $sellerRevenue = 0; $sellerLowStock = 0; $sellerTopProduct = null; $sellerWeeklySales = [];
if ($user['role'] === 'seller' || $user['role'] === 'admin') {
    try {
        // Total items sold
        $s = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id=p.id WHERE p.user_id=? AND o.status='completed'"); $s->execute([$user['id']]); $sellerTotalSold = (int)$s->fetchColumn();
    } catch(PDOException $e) {}
    try {
        // Total revenue
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND status='completed'"); $s->execute([$user['id']]); $sellerRevenue = (float)$s->fetchColumn();
    } catch(PDOException $e) {}
    // Low stock products
    $sellerLowStock = 0;
    foreach ($products as $p) { if ($p['quantity'] > 0 && $p['quantity'] <= 5 && $p['status'] === 'approved') $sellerLowStock++; }
    try {
        // Top performing product
        $s = $pdo->prepare("SELECT title, views, quantity FROM products WHERE user_id=? AND status='approved' ORDER BY views DESC LIMIT 1"); $s->execute([$user['id']]); $sellerTopProduct = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch(PDOException $e) {}
    // Weekly sales data (last 7 days from transactions)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = date('M d', strtotime($date));
        $date_cast = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "::DATE" : "";
        $date_func = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "" : "DATE";
        
        try {
            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
                ? "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND created_at::DATE=?"
                : "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='sale' AND CAST(created_at AS DATE)=?";
            $s = $pdo->prepare($sql); $s->execute([$user['id'], $date]); $amt = (float)$s->fetchColumn();
        } catch(PDOException $e) { $amt = 0; }
        try {
            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' 
                ? "SELECT COALESCE(SUM(views),0) FROM products WHERE user_id=? AND updated_at::DATE=?"
                : "SELECT COALESCE(SUM(views),0) FROM products WHERE user_id=? AND CAST(updated_at AS DATE)=?";
            $s2 = $pdo->prepare($sql); $s2->execute([$user['id'], $date]); $vw = (int)$s2->fetchColumn();
        } catch(PDOException $e) { $vw = 0; }
        $sellerWeeklySales[] = ['label' => $dayLabel, 'sales' => $amt, 'views' => $vw];
    }
}

// Chart data for sellers
$chart_data = $sellerWeeklySales ?: [];
if (empty($chart_data)) {
    for ($i = 6; $i >= 0; $i--) {
        $chart_data[] = ['label' => date('M d', strtotime("-$i days")), 'sales' => 0, 'views' => 0];
    }
}

// --- FETCH ORDERS ---
$buyer_orders = [];
$seller_orders = [];
try {
    if ($user['role'] === 'buyer' || $user['role'] === 'admin') {
        $s = $pdo->prepare("SELECT o.*, p.title as product_title, p.price as product_price, s.username as seller_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users s ON o.seller_id=s.id WHERE o.buyer_id=? ORDER BY o.created_at DESC");
        $s->execute([$user['id']]); $buyer_orders = $s->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($user['role'] === 'seller' || $user['role'] === 'admin') {
        $s = $pdo->prepare("SELECT o.*, p.title as product_title, p.price as product_price, b.username as buyer_name FROM orders o JOIN products p ON o.product_id=p.id JOIN users b ON o.buyer_id=b.id WHERE o.seller_id=? ORDER BY o.created_at DESC");
        $s->execute([$user['id']]); $seller_orders = $s->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {}

require_once 'includes/ai_recommendations.php';

require_once 'includes/header.php';
?>
<style>
/* ═══════════════════════════════════════
   FULL-SCREEN DASHBOARD LAYOUT
   ═══════════════════════════════════════ */
.container { 
    max-width: none !important; 
    width: 96% !important; 
    padding-left: 2rem !important; 
    padding-right: 2rem !important; 
}

/* ═══════════════════════════════════════
   SELLER DASHBOARD LAYOUT FIXES
   ═══════════════════════════════════════ */
.product-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.03); /* Subtle backdrop */
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.product-row:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Ensure actions don't overflow the box */
.product-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    flex-shrink: 0;
    align-items: center;
}

@media (max-width: 900px) {
    .product-row {
        flex-direction: column;
        align-items: stretch;
        gap: 1.25rem;
    }
    .product-actions {
        justify-content: flex-start;
        width: 100%;
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }
}

/* Tooltip stabilization — Anchor to the button's relative box */
.boost-tooltip {
    position: relative;
    display: inline-block;
}
.boost-info {
    visibility: hidden;
    width: 240px;
    background: #1d1d1f; /* Clean Apple Dark */
    color: #fff;
    text-align: left;
    padding: 1rem;
    border-radius: 14px;
    position: absolute;
    z-index: 10000;
    bottom: 140%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: all 0.3s ease;
    font-size: 0.78rem;
    line-height: 1.5;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: var(--shadow-lg);
    pointer-events: none;
}
.boost-tooltip:hover .boost-info {
    visibility: visible;
    opacity: 1;
    bottom: 125%;
}

/* Discount Input Styling */
.discount-input-inline {
    width: 65px;
    padding: 0.45rem 0.75rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-main);
    font-size: 0.85rem;
    font-weight: 600;
    transition: border-color 0.2s;
}
.discount-input-inline:focus {
    border-color: var(--primary);
    outline: none;
}

/* Responsive Dashboard Product Grid */
@media (max-width: 768px) {
    #detailed-product-grid {
        padding: 0.75rem !important;
        margin: 0 -0.5rem !important;
    }
    #detailed-product-grid .product-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    #detailed-product-grid .product-card {
        min-height: auto !important;
        border-radius: 12px !important;
    }
    #detailed-product-grid .product-body {
        padding: 0.6rem !important;
    }
    #detailed-product-grid h4 {
        font-size: 0.8rem !important;
        line-height: 1.25 !important;
        min-height: 2em !important;
    }
    .products_section_summary {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1.5rem !important;
    }
    .inventory-slots-card {
        width: 100% !important;
        text-align: left !important;
    }
}

/* ── PROFILE PIC CENTERING FIX ── */
.profile-pic-lg {
    width: 110px;
    height: 110px;
    border-radius: 50% !important;
    object-fit: cover;
    display: block !important;
    margin: 0 auto 1.25rem !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 3px solid #fff;
    transition: transform 0.3s;
}

/* ── TOAST NOTIFICATION ── */
.toast-notify {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(29, 29, 31, 0.95);
    color: #fff;
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    z-index: 10000;
    transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1), opacity 0.5s;
    opacity: 0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 8px;
}
.toast-notify.visible {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}
</style>
<?php

if($msg): ?><div class="alert alert-success fade-in"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Global Announcements -->
<?php if(count($announcements) > 0): ?>
<div class="announcements-area mb-3">
    <?php foreach($announcements as $ann): ?>
        <div class="alert alert-<?= $ann['type'] ?> fade-in" style="margin-bottom:0.5rem; display:flex; align-items:center; gap:10px;">
            <span style="font-size:1.2rem;">📢</span>
            <div style="flex:1;">
                <strong>Global Update:</strong> <?= nl2br(htmlspecialchars($ann['message'])) ?>
                <small style="display:block; font-size:0.7rem; opacity:0.7;"><?= date('M d, H:i', strtotime($ann['created_at'])) ?> by Admin</small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($user['vacation_mode']): ?>
<div class="alert alert-warning">🏝️ <strong>Vacation Mode ON</strong> — Your listings are hidden from buyers.
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="toggle_vacation">
        <?= csrf_field() ?>
        <button type="submit" style="background:none; border:none; color:inherit; text-decoration:underline; cursor:pointer;">Disable</button>
    </form>
</div>
<?php endif; ?>

<div class="dashboard-grid" style="display:grid; grid-template-columns:300px 1fr; gap:2rem;">
    <!-- SIDEBAR -->
    <div>
        <!-- Profile Card -->
        <div class="glass fade-in" style="padding:2rem; text-align:center; margin-bottom:1.5rem;">
            <?php $tierClass = 'profile-pic-' . ($user['role'] === 'seller' ? ($user['seller_tier'] ?: 'basic') : 'basic'); ?>
            <?php if($user['profile_pic']): ?>
                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>" class="profile-pic profile-pic-lg profile-pic-previewable <?= $tierClass ?> mb-2" style="cursor:pointer;" alt="<?= htmlspecialchars($user['username']) ?>">
            <?php else: ?>
                <div class="profile-pic profile-pic-lg <?= $tierClass ?> mb-2" style="display:flex;align-items:center;justify-content:center;background:rgba(99,102,241,0.2);color:var(--primary);font-size:2.5rem;font-weight:700;margin:0 auto;">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <h3><?= htmlspecialchars($user['username']) ?></h3>
            <div class="mb-1">
                <?php if($user['role'] === 'seller'): ?>
                    <?= getBadgeHtml($pdo, $user['seller_tier'] ?: 'basic') ?>
                <?php elseif($user['role'] === 'admin'): ?>
                    <span class="badge badge-gold">👑 Admin</span>
                <?php else: ?>
                    <span class="badge badge-blue">🛒 Buyer</span>
                <?php endif; ?>

                <?php if($user['verified']): ?><span class="badge badge-approved" style="margin-left:4px;">✓ Verified</span><?php endif; ?>
            </div>
            
            <?php if(!empty($user['faculty'])): ?><p class="text-muted" style="font-size:0.78rem; margin-top:0.3rem;">🎓 <?= htmlspecialchars($user['faculty']) ?></p><?php endif; ?>
            <?php if($user['department']): ?><p class="text-muted" style="font-size:0.85rem;"><?= htmlspecialchars($user['department']) ?> · L<?= htmlspecialchars($user['level']) ?></p><?php endif; ?>
            <?php if($user['hall']): ?><p class="text-muted" style="font-size:0.8rem;">🏠 <?= htmlspecialchars($user['hall']) ?></p><?php endif; ?>
            <?php if($user['phone']): ?><p class="text-muted" style="font-size:0.8rem;">📱 <?= htmlspecialchars($user['phone']) ?></p><?php endif; ?>
            
            <!-- Social Links -->
            <div class="flex gap-1 mt-2" style="justify-content:center;">
                <?php if($user['whatsapp']): ?><a href="https://wa.me/<?= $user['whatsapp'] ?>" target="_blank" class="btn btn-outline btn-sm">WhatsApp</a><?php endif; ?>
                <?php if($user['instagram']): ?><a href="https://instagram.com/<?= $user['instagram'] ?>" target="_blank" class="btn btn-outline btn-sm">IG</a><?php endif; ?>
            </div>
            
            <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-direction:column;">
                <a href="edit_profile.php" class="react-liquid-btn" data-label="Edit Profile"></a>
                <?php if(isSeller()): ?>
                    <form method="POST" style="display:flex; flex-direction:column; gap:0.5rem;">
                        <input type="hidden" name="action" value="toggle_vacation">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm" style="justify-content:center;"><?= $user['vacation_mode'] ? '☀️ End Vacation' : '🏝️ Vacation Mode' ?></button>
                    </form>
                    <?php if($user['seller_tier'] !== 'premium'): ?>
                        <button type="button" class="btn btn-gold btn-sm" style="justify-content:center;" onclick="toggleUpgradeModal(true);">🚀 Upgrade Account</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <style>
            body.modal-open {
                overflow: hidden;
                position: fixed;
                width: 100%;
            }
            .upgrade-card-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.5rem;
                width: 100%;
            }
            .upgrade-modal-content {
                width: 100%;
                max-width: 1000px;
                padding: 2.5rem;
                border-radius: 32px;
                position: relative;
                background: var(--bg);
                backdrop-filter: blur(25px);
                -webkit-backdrop-filter: blur(25px);
                border: 1px solid var(--border);
                color: var(--text-main);
                box-shadow: var(--shadow-lg);
            }
            .dark-mode .upgrade-modal-content {
                background: rgba(20,20,20,0.85);
                border-color: rgba(255,255,255,0.1);
            }
            .tier-card {
                padding: 2rem;
                border-radius: 28px;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                background: rgba(0,0,0,0.02);
                border: 1px solid var(--border);
                position: relative;
            }
            .dark-mode .tier-card {
                background: rgba(255,255,255,0.05);
            }
            .tier-card.popular {
                background: rgba(0,113,227,0.04);
                border-color: rgba(0,113,227,0.3);
            }
            .dark-mode .tier-card.popular {
                background: rgba(0,113,227,0.15);
            }
            @media (max-width: 640px) {
                .upgrade-modal-content {
                    padding: 2rem 1rem 5rem;
                    border-radius: 32px 32px 0 0;
                    margin-top: auto;
                    height: 90vh;
                    overflow-y: auto;
                }
                .upgrade-card-grid {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                }
                .tier-title { font-size: 1.35rem !important; }
                .tier-price { font-size: 2.2rem !important; }
                .product-grid { 
                    grid-template-columns: 1fr !important; 
                    gap: 1rem !important;
                }
                .product-card { min-height: auto !important; }
                .dashboard-grid { grid-template-columns: 1fr !important; gap: 1rem !important; }
            }
        </style>

        <script>
            function toggleUpgradeModal(show) {
                const modal = document.getElementById('upgradeModal');
                if (show) {
                    modal.style.display = 'flex';
                    document.body.classList.add('modal-open');
                } else {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            }
        </script>

        <!-- Upgrade Modal -->
        <div id="upgradeModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999999; align-items:center; justify-content:center; backdrop-filter:blur(15px); overflow-y:auto; -webkit-overflow-scrolling: touch;">
            <?php
            $stmt = $pdo->query("SELECT * FROM account_tiers ORDER BY price ASC");
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="glass upgrade-modal-content fade-in">
                 <button type="button" onclick="toggleUpgradeModal(false);" style="position:absolute; top:15px; right:15px; background:rgba(255,255,255,0.1); border:none; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:var(--text-main); cursor:pointer; z-index:10;">&times;</button>
                 
                 <div class="text-center mb-4 pt-2">
                    <h2 style="font-weight:800; letter-spacing:-0.03em;">Upgrade Your Business</h2>
                    <p class="text-muted" style="font-size:0.9rem;">Elevate your campus brand with premium verified tiers.</p>
                 </div>
                 
                 <div class="upgrade-card-grid">
                    <?php foreach ($tiers as $tier): ?>
                        <?php 
                            $active = ($user['seller_tier'] ?: 'basic') === $tier['tier_name']; 
                            $is_popular = $tier['tier_name'] === 'pro';
                        ?>
                        <div class="tier-card <?= $is_popular ? 'popular' : '' ?>" style="<?= $active ? 'border-color:var(--primary);' : '' ?>">
                            <?php if($active): ?><span class="badge badge-blue" style="position:absolute; top:20px; right:20px;">Active Plan</span><?php endif; ?>
                            <?php if($is_popular && !$active): ?><span class="badge badge-gold" style="position:absolute; top:20px; right:20px;">Best Value</span><?php endif; ?>
                            
                            <h3 class="tier-title" style="text-transform:capitalize; margin-bottom:0.4rem; font-weight:800; font-size:1.5rem;"><?= htmlspecialchars($tier['tier_name']) ?></h3>
                            <div class="tier-price" style="font-size:2.8rem; font-weight:900; margin-bottom:1.5rem; color:var(--primary); letter-spacing:-0.05em;">
                                ₵<?= number_format($tier['price'], 0) ?>
                                <p style="font-size:0.9rem; margin-top:0.3rem; margin-bottom:0; color:var(--text-muted); font-weight:600; letter-spacing:normal;">
                                    Valid for <?= htmlspecialchars($tier['duration']) ?> month<?= $tier['duration'] == 1 ? '' : 's' ?>
                                </p>
                            </div>
                            
                            <ul style="list-style:none; padding:0; margin-bottom:1.5rem; flex-grow:1;">
                                <?php
                                $d_bens = json_decode($tier['benefits'] ?? '[]', true) ?: [];
                                foreach($d_bens as $b): 
                                ?>
                                <li style="margin-bottom:0.8rem; font-size:0.92rem; display:flex; gap:10px; font-weight:500;">
                                    <span style="color:var(--primary); font-weight:800;">✓</span> <?= htmlspecialchars($b) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <?php if(!$active): ?>
                                <?php if($tier['price'] > 0): ?>
                                    <button type="button" onclick="payWithPaystack('<?= $tier['tier_name'] ?>', <?= $tier['price'] ?>)" class="btn <?= $is_popular ? 'btn-primary' : 'btn-outline' ?>" style="width:100%; border-radius:14px; padding:1.2rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;box-shadow:0 10px 20px rgba(0,113,227,0.1);">Upgrade to <?= ucfirst($tier['tier_name']) ?></button>
                                <?php else: ?>
                                    <form method="POST" style="width:100%;">
                                        <input type="hidden" name="action" value="request_<?= htmlspecialchars($tier['tier_name']) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn <?= $is_popular ? 'btn-primary' : 'btn-outline' ?>" style="width:100%; text-align:center; border-radius:14px; padding:1.2rem; font-weight:800;">Get Started Free</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <div style="width:100%; text-align:center; background:rgba(0,113,227,0.08); padding:1rem; border-radius:14px; font-weight:800; color:var(--primary); border:2px solid var(--primary);">Active Plan</div>
                                    <?php if($tier['tier_name'] !== 'basic'): ?>
                                        <form method="POST" style="width:100%;">
                                            <input type="hidden" name="action" value="cancel_tier">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.8rem; justify-content:center; color:var(--danger); border-color:rgba(255,59,48,0.2); font-weight:700; width:100%;" onclick="return confirm('Request to downgrade to Basic? Your limits will be reduced.')">Downgrade to Basic</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                 </div>
            </div>
        </div>


        <!-- Wallet -->
        <div class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem; margin-top:1.5rem;">
            <p class="text-muted" style="font-size:0.8rem;">Wallet Balance</p>
            <h2 style="color:var(--success); font-size:2rem; font-weight:800;">₵<?= number_format($user['balance'], 2) ?></h2>
        </div>

        <!-- Recent Transactions -->
        <div id="transactions_section" class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h4 class="mb-2">Recent Activity</h4>
            <?php if(count($transactions) > 0): ?>
                <?php foreach(array_slice($transactions, 0, 5) as $tx): ?>
                    <?php $is_credit = in_array($tx['type'], ['deposit', 'sale', 'referral']); ?>
                    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border);">
                        <div>
                            <a href="receipt.php?ref=<?= urlencode($tx['reference']) ?>" style="font-size:0.85rem; font-weight:600; color:var(--text-main); text-decoration:none;">
                                <?= htmlspecialchars($tx['description'] ?? ucfirst($tx['type'])) ?>
                            </a>
                            <p style="font-size:0.7rem; color:var(--text-muted);"><?= date('M d', strtotime($tx['created_at'])) ?></p>
                        </div>
                        <span style="color:<?= $is_credit ? 'var(--mint)' : 'var(--danger)' ?>; font-weight:700; font-size:0.85rem;">
                            <?= $is_credit ? '+' : '-' ?>₵<?= number_format($tx['amount'], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div style="text-align:center; padding-top:1rem;">
                    <a href="transactions.php" class="text-muted" style="font-size:0.75rem; text-decoration:underline;">View All History</a>
                </div>
            <?php else: ?>
                <p class="text-muted" style="font-size:0.85rem;">No history yet.</p>
            <?php endif; ?>
        </div>

        <!-- Contact Admin -->
        <div class="glass fade-in" style="padding:1.5rem; margin-top:1.5rem;">
            <h4 class="mb-2">📞 Contact Admin</h4>
            <p class="text-muted" style="font-size:0.8rem; margin-bottom:1rem;">Need clarification? Chat directly with the platform administrator.</p>
            <form method="POST" action="chat.php?action=send_fast" class="flex-column gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="receiver_id" value="1">
                <textarea name="message" placeholder="Type your question here..." class="form-control" style="font-size:0.85rem; min-height:80px; padding:0.75rem; border-radius:12px;"></textarea>
                <button class="btn btn-primary btn-sm" style="width:100%; border-radius:10px;">Send Message</button>
            </form>
            <a href="chat.php?user=1" class="btn btn-outline btn-sm mt-1" style="width:100%; justify-content:center; border-radius:10px;">Open Chat History</a>
        </div>
    </div>

    <!-- MAIN AREA -->
    <div>
        <!-- ROLE-SPECIFIC ANALYTICS -->
        <?php if($user['role'] === 'seller' || $user['role'] === 'admin'): ?>
        <!-- SELLER STATS -->
        <div class="stat-grid mb-3 fade-in">
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $totalProducts ?></div><div class="stat-label">Total Products</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);"><?= $totalApproved ?></div><div class="stat-label">Active Listings</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--mint);"><?= $sellerTotalSold ?></div><div class="stat-label">Items Sold</div></div></a>
            <a href="#transactions_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">₵<?= number_format($sellerRevenue, 2) ?></div><div class="stat-label">Total Revenue</div></div></a>
            <a href="#products_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--warning);"><?= $totalPending ?></div><div class="stat-label">Pending Approval</div></div></a>
            <a href="#analytics_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val"><?= $viewsTotal ?></div><div class="stat-label">Total Views</div></div></a>
        </div>

        <!-- Seller Insights Row -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;" class="fade-in grid-2-cols">
            <?php if($sellerLowStock > 0): ?>
            <div class="glass" style="padding:1.25rem; border-left:4px solid #ff9500;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">⚠️</span>
                    <h4 style="font-size:0.9rem; margin:0;">Low Stock Alert</h4>
                </div>
                <p style="font-size:2rem; font-weight:800; color:#ff9500;"><?= $sellerLowStock ?></p>
                <p class="text-muted" style="font-size:0.78rem;">products with ≤5 units</p>
            </div>
            <?php else: ?>
            <div class="glass" style="padding:1.25rem; border-left:4px solid var(--success);">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">✅</span>
                    <h4 style="font-size:0.9rem; margin:0;">Stock Status</h4>
                </div>
                <p style="font-size:0.85rem; color:var(--success); font-weight:600;">All products well stocked</p>
            </div>
            <?php endif; ?>

            <div class="glass" style="padding:1.25rem; border-left:4px solid var(--primary);">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem;">
                    <span style="font-size:1.3rem;">🏆</span>
                    <h4 style="font-size:0.9rem; margin:0;">Top Product</h4>
                </div>
                <?php if($sellerTopProduct): ?>
                    <p style="font-size:0.95rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($sellerTopProduct['title']) ?></p>
                    <p class="text-muted" style="font-size:0.78rem;">👁 <?= $sellerTopProduct['views'] ?> views · Stock: <?= $sellerTopProduct['quantity'] ?></p>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">No products yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weekly Revenue + Views Chart -->
        <div id="analytics_section" class="glass fade-in" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h4 class="mb-2">📊 Weekly Performance</h4>
            <canvas id="analyticsChart" height="100"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('analyticsChart');
            if(ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($chart_data, 'label')) ?>,
                        datasets: [
                            {
                                label: 'Revenue (₵)',
                                data: <?= json_encode(array_column($chart_data, 'sales')) ?>,
                                backgroundColor: 'rgba(0,113,227,0.4)',
                                borderColor: '#0071e3',
                                borderWidth: 2,
                                borderRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Views',
                                data: <?= json_encode(array_column($chart_data, 'views')) ?>,
                                type: 'line',
                                borderColor: '#34c759',
                                backgroundColor: 'rgba(52,199,89,0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: true,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'left', ticks: { color: '#94a3b8' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                            y1: { beginAtZero: true, position: 'right', ticks: { color: '#34c759' }, grid: { drawOnChartArea: false } },
                            x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
                        },
                        plugins: { legend: { labels: { color: 'var(--text-main)', usePointStyle: true } } }
                    }
                });
            }
        });
        </script>
        <?php endif; ?>

        <?php if($user['role'] === 'buyer'): ?>
        <!-- BUYER STATS -->
        <div class="stat-grid mb-3 fade-in">
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $buyerItemsBought ?></div><div class="stat-label">Items Bought</div></div></a>
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">₵<?= number_format($buyerTotalSpent, 2) ?></div><div class="stat-label">Total Spent</div></div></a>
            <a href="#buyer_recent_purchases" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);"><?= count($buyerRecentPurchases) ?></div><div class="stat-label">Recent Orders</div></div></a>
            <a href="javascript:void(0)" onclick="if(typeof openSideCart === 'function') openSideCart();" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--mint);" id="dash-cart-count">0</div><div class="stat-label">Cart Items</div></div></a>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const updateDashCart = () => {
                    let d = localStorage.getItem('cm_cart');
                    let c = d ? JSON.parse(d) : [];
                    let el = document.getElementById('dash-cart-count');
                    if(el) el.innerText = c.length;
                };
                updateDashCart();
                window.addEventListener('storage', updateDashCart);
                // Also hook into custom cart events if any
            });
        </script>

        <!-- Buyer Insights -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;" class="fade-in grid-2-cols">
            <!-- Recent Purchases -->
            <div id="buyer_recent_purchases" class="glass" style="padding:1.25rem;">
                <h4 style="font-size:0.95rem; margin-bottom:0.75rem;">🛍️ Recent Purchases</h4>
                <?php if(count($buyerRecentPurchases) > 0): ?>
                    <?php foreach($buyerRecentPurchases as $bp): ?>
                    <div style="display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0; border-bottom:1px solid var(--border);">
                        <?php if(!empty($bp['img'])): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($bp['img'])) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;" alt="">
                        <?php else: ?>
                            <div style="width:36px;height:36px;background:rgba(0,0,0,0.1);border-radius:6px;display:flex;align-items:center;justify-content:center;">📦</div>
                        <?php endif; ?>
                        <div style="flex:1; overflow:hidden;">
                            <p style="font-size:0.82rem; font-weight:600; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?= htmlspecialchars($bp['title'] ?? 'Product') ?></p>
                            <p class="text-muted" style="font-size:0.72rem;"><?= date('M d', strtotime($bp['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">No purchases yet. Start shopping!</p>
                <?php endif; ?>
            </div>

            <!-- Favorite Categories -->
            <div class="glass" style="padding:1.25rem;">
                <h4 style="font-size:0.95rem; margin-bottom:0.75rem;">❤️ Favorite Categories</h4>
                <?php if(count($buyerFavCategories) > 0): ?>
                    <?php foreach($buyerFavCategories as $fc): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; border-bottom:1px solid var(--border);">
                        <span class="badge badge-blue" style="font-size:0.75rem;"><?= htmlspecialchars($fc['category']) ?></span>
                        <span style="font-size:0.8rem; font-weight:700;"><?= $fc['cnt'] ?> orders</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">Buy products to discover your favorites!</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($user['role'] === 'seller' || $user['role'] === 'admin'): ?>
        <!-- Products Section (Consolidated Card) -->
        <div id="products_section" class="fade-in mb-3">
            <div class="glass" style="padding:2rem; border-radius:24px; position:relative; overflow:hidden; background:linear-gradient(135deg, rgba(0,113,227,0.1) 0%, rgba(20,20,20,0) 100%); border:1px solid rgba(0,113,227,0.2);">
                <div class="products_section_summary" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.5rem;">
                            <span style="font-size:1.8rem;">📦</span>
                            <h3 style="margin:0; font-size:1.5rem; font-weight:800;">My Inventory</h3>
                        </div>
                        <p class="text-muted" style="font-size:0.9rem;">You have <strong><?= count($products) ?></strong> products listed on the marketplace.</p>
                        <div style="margin-top:1.25rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn btn-primary" onclick="toggleDetailedProducts()" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700;">Manage Products</button>
                            <?php if(canAddProduct($pdo, $user['id'])): ?>
                                <a href="add_product.php" class="btn btn-outline" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700;">+ New Product</a>
                            <?php endif; ?>
                            <button class="btn btn-outline" onclick="copyProductLink()" style="padding:0.75rem 1.5rem; border-radius:12px; font-weight:700; color:var(--primary); border-color:var(--primary);">🔗 Share My Shop</button>
                        </div>
                    </div>
                    
                    <div class="inventory-slots-card" style="text-align:right;">
                        <div style="padding:1.25rem; background:rgba(255,255,255,0.05); border-radius:20px; border:1px solid rgba(255,255,255,0.1); display:inline-block;">
                            <p class="text-muted" style="font-size:0.75rem; margin-bottom:0.2rem;">Inventory Slots</p>
                            <?php 
                                $tier = $user['seller_tier'] ?: 'basic';
                                $limit = getAccountTiers($pdo)[$tier]['product_limit'] ?? 2;
                                $usage_pct = ($count_prods = count($products)) / $limit * 100;
                            ?>
                            <div style="font-size:1.4rem; font-weight:900; color:var(--text-main); line-height:1;"><?= $count_prods ?> / <?= $limit ?></div>
                            <div style="width:100px; height:6px; background:rgba(0,0,0,0.1); border-radius:3px; margin-top:8px; overflow:hidden;">
                                <div style="width:<?= min(100, $usage_pct) ?>%; height:100%; background:var(--primary);"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Grid (Hidden by Default) -->
            <div id="detailed-product-grid" class="glass mt-2" style="display:none; padding:2rem; border-radius:24px; animation: slideDown 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);">
                <div class="flex-between mb-4">
                    <h4 style="font-size:1.2rem; margin:0;">Product Catalog</h4>
                    <button class="btn btn-outline btn-sm" onclick="toggleDetailedProducts()">Close View</button>
                </div>

                <?php if(count($products) > 0): ?>
                    <div class="product-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:1.25rem;">
                        <?php foreach($products as $p): ?>
                            <div class="glass product-card" style="display:flex; flex-direction:column; background:var(--bg); border:1px solid var(--border); border-radius:22px; overflow:hidden; transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); min-height:400px; position:relative;">
                                <div class="product-img-wrap" style="aspect-ratio: 1 / 1; position:relative; overflow:hidden;">
                                    <?php if($p['main_image']): ?>
                                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" class="product-img" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.05); color:var(--text-muted); font-size:0.75rem;">No Image</div>
                                    <?php endif; ?>
                                    
                                    <div style="position:absolute; top:12px; left:12px; display:flex; flex-direction:column; gap:6px;">
                                        <?php
                                            $statusColor = match($p['status']) {
                                                'approved' => '#34c759',
                                                'pending' => '#ff9500',
                                                'paused' => '#8e8e93',
                                                'sold' => '#ff3b30',
                                                default => '#0071e3'
                                            };
                                        ?>
                                        <span class="badge" style="background:<?= $statusColor ?>; color:#fff; border:none; font-size:0.65rem; padding:4px 10px; font-weight:700; backdrop-filter:blur(10px);"><?= strtoupper($p['status']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="product-body" style="padding:1.25rem; flex-grow:1; display:flex; flex-direction:column;">
                                    <h4 style="font-size:1.05rem; font-weight:700; margin-bottom:0.4rem;"><?= htmlspecialchars($p['title']) ?></h4>
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                                        <p style="font-size:1.25rem; font-weight:800; color:var(--primary); margin:0;">₵<?= number_format($p['price'], 2) ?></p>
                                        <span class="text-muted" style="font-size:0.75rem; font-weight:600;">Qty: <?= $p['quantity'] ?></span>
                                    </div>

                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.6rem; margin-top:auto;">
                                        <?php if($p['status'] === 'approved'): ?>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="mark_sold">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:0.6rem; justify-content:center; color:#ff3b30; border-color:rgba(255,59,48,0.1);" onclick="return confirm('Mark as Sold Out?')">Sold Out</button>
                                            </form>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="pause">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:0.6rem; justify-content:center;">Pause</button>
                                            </form>
                                            <a href="generate_flyer.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="grid-column: span 2; font-size:0.8rem; padding:0.6rem; justify-content:center; border-radius:12px;">📸 Flyer / Promo</a>
                                            <?php if(!$p['boosted_until'] || strtotime($p['boosted_until']) < time()): ?>
                                                <form method="POST" style="display:contents;">
                                                    <input type="hidden" name="action" value="boost">
                                                    <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-gold btn-sm" style="grid-column: span 2; font-size:0.8rem; padding:0.6rem; justify-content:center; border-radius:12px;" onclick="return confirm('Boost for 24h?')">⚡ Boost Item</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif($p['status'] === 'paused'): ?>
                                            <form method="POST" style="display:contents;">
                                                <input type="hidden" name="action" value="unpause">
                                                <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-success btn-sm" style="grid-column: span 2; font-size:0.8rem; justify-content:center; border-radius:12px;">Resume Listing</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:contents;">
                                            <input type="hidden" name="action" value="request_delete">
                                            <input type="hidden" name="pid" value="<?= $p['id'] ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline btn-sm" style="grid-column: span 2; font-size:0.7rem; padding:0.4rem; color:var(--text-muted); justify-content:center; border-style:dashed; margin-top:0.5rem; opacity:0.6;" onclick="return confirm('Request Deletion?')">Remove listing</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted" style="padding:4rem;">No products found.</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function toggleDetailedProducts() {
                const grid = document.getElementById('detailed-product-grid');
                if (grid.style.display === 'none') {
                    grid.style.display = 'block';
                    grid.scrollIntoView({ behavior: 'smooth' });
                } else {
                    grid.style.display = 'none';
                }
            }

            function copyProductLink() {
                const url = window.location.origin + window.location.pathname.replace('dashboard.php', 'index.php') + '?seller=<?= urlencode($user['username']) ?>';
                
                // --- ADVANCED CLIPBOARD FALLBACK ---
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(() => {
                        showDashToast('✅ Shop link copied! Share it on WhatsApp.');
                    }).catch(err => fallbackCopy(url));
                } else {
                    fallbackCopy(url);
                }
            }

            function fallbackCopy(text) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed"; top: 0; left: 0;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showDashToast('✅ Shop link copied! Share it on WhatsApp.');
                } catch (err) {
                    alert('Could not copy link. Manually copy: ' + text);
                }
                document.body.removeChild(textArea);
            }
        </script>
        <?php endif; ?>

        <!-- ORDERS VIEW (SELLER) -->
        <?php if($user['role'] === 'seller' || $user['role'] === 'admin'): ?>
        <div id="seller_orders" class="glass fade-in" style="margin-bottom:2rem; padding:2rem;">
            <h3 class="mb-3">📦 Order Management</h3>
            <?php if(count($seller_orders) > 0): ?>
                <div class="flex-column gap-1">
                    <?php foreach($seller_orders as $ord): ?>
                    <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border); padding:1rem; border-radius:12px;">
                        <div class="flex-between mb-1">
                            <strong>#ORDER-<?= $ord['id'] ?> &bull; <?= htmlspecialchars($ord['product_title']) ?></strong>
                            <span class="badge" style="background:#0071e3;color:#fff;">₵<?= number_format($ord['product_price'], 2) ?></span>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem;">Buyer: <strong><?= htmlspecialchars($ord['buyer_name']) ?></strong> &bull; Date: <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?></p>
                        
                        <div style="margin-top:0.75rem;">
                            <?php if($ord['status'] === 'ordered'): ?>
                                <span class="badge badge-pending mb-1">Status: Pending</span>
                                <a href="?action=deliver_order&oid=<?= $ord['id'] ?>" class="btn btn-success btn-sm mt-1" onclick="return confirm('Confirm Item Sold?');">Confirm Item Sold</a>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">💬 Message Buyer</a>
                            <?php elseif($ord['status'] === 'delivered'): ?>
                                <span class="badge badge-approved">Status: Sold (Seller Confirmed)</span>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">💬 Message Buyer</a>
                            <?php elseif($ord['status'] === 'completed'): ?>
                                <span class="badge" style="background:var(--success); color:#fff;">✓ Status: Completed</span>
                                <a href="chat.php?user=<?= $ord['buyer_id'] ?>" class="btn btn-outline btn-sm mt-1">💬 Chat History</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:1rem;">No orders to manage.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- BUYER ORDERS -->
        <?php if($user['role'] === 'buyer' || $user['role'] === 'admin'): ?>
        <div id="buyer_orders" class="glass fade-in mt-3" style="padding:2rem;">
            <div class="flex-between mb-3">
                <h3 style="margin:0;">🛍️ My Orders</h3>
                <?php if(hasUnreviewedOrders($pdo, $user['id'])): ?>
                    <span class="badge badge-rejected" style="padding:6px 12px; font-weight:800;">🔒 ACTION REQUIRED: Leave Reviews</span>
                <?php endif; ?>
            </div>
            <?php if(count($buyer_orders) > 0): ?>
                <div class="flex-column gap-1">
                    <?php foreach($buyer_orders as $ord): ?>
                    <div style="background:rgba(0,0,0,0.2); border:1px solid var(--border); padding:1rem; border-radius:12px;">
                        <div class="flex-between mb-1">
                            <strong><?= htmlspecialchars($ord['product_title']) ?></strong>
                            <span class="badge" style="background:#0071e3;color:#fff;">₵<?= number_format($ord['product_price'], 2) ?></span>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem;">Seller: <strong><?= htmlspecialchars($ord['seller_name']) ?></strong> &bull; <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?></p>
                        
                        <div style="margin-top:0.75rem;">
                            <?php if($ord['status'] === 'ordered'): ?>
                                <span class="badge badge-pending">Status: Pending (Awaiting Seller)</span>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-primary btn-sm mt-1">💬 Message Seller</a>
                            <?php elseif($ord['status'] === 'delivered'): ?>
                                <span class="badge badge-approved mb-1">Status: Sold (Seller Confirmed)</span><br>
                                <a href="?action=receive_order&oid=<?= $ord['id'] ?>" class="btn btn-success btn-sm mt-1" onclick="return confirm('Confirm Item Received?');">Confirm Item Received</a>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-outline btn-sm mt-1">💬 Message Seller</a>
                            <?php elseif($ord['status'] === 'completed'): ?>
                                <span class="badge" style="background:var(--success); color:#fff;">✓ Status: Completed</span>
                                <a href="product.php?id=<?= $ord['product_id'] ?>#review" class="btn btn-outline btn-sm" style="margin-left:0.5rem;">Submit Review</a>
                                <a href="chat.php?user=<?= $ord['seller_id'] ?>" class="btn btn-outline btn-sm">💬 Chat History</a>
                                <a href="#" onclick="document.getElementById('dispute_form_<?= $ord['id'] ?>').style.display='block'; return false;" class="text-muted" style="font-size:0.75rem; margin-left:1rem; text-decoration:underline;">Report Issue</a>
                                <div id="dispute_form_<?= $ord['id'] ?>" style="display:none; margin-top:1rem; padding:1rem; background:rgba(255,59,48,0.05); border-radius:12px; border:1px solid rgba(255,59,48,0.1);">
                                    <p style="font-size:0.8rem; font-weight:700; color:var(--danger); margin-bottom:0.5rem;">Report Dispute to Admin</p>
                                    <form method="POST" action="?action=submit_dispute&oid=<?= $ord['id'] ?>" class="flex-column gap-1">
                                        <textarea name="reason" placeholder="Explain the issue..." class="form-control" style="font-size:0.8rem; min-height:60px;"></textarea>
                                        <div class="flex gap-1" style="margin-top:0.5rem;">
                                            <button class="btn btn-danger btn-sm">Report</button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('dispute_form_<?= $ord['id'] ?>').style.display='none';">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center" style="padding:1rem;">You have not placed any orders yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ADDED: Buyer Cart / Order Summary Section -->
<?php if($user['role'] === 'buyer'): ?>
<div class="glass fade-in mt-3" style="padding:2rem;">
    <h3 class="mb-2">🛒 My Cart</h3>
    
    <div id="dash-cart-items" style="display:flex; flex-direction:column; gap:1rem;"></div>
    
    <div id="dash-cart-empty" style="text-align:center; padding:1.5rem; display:none;">
        <p class="text-muted" style="font-size:0.9rem;">Your cart is empty. Browse products to add items.</p>
    </div>
    
    <div id="dash-cart-footer" style="display:none; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <p style="font-size:0.9rem; color:var(--text-muted);">Total</p>
                <p id="dash-cart-total" style="font-size:1.8rem; font-weight:800; color:var(--primary);">₵0.00</p>
            </div>
            <div style="display:flex; gap:0.75rem;">
                <button class="btn btn-outline btn-sm" onclick="cmCart.clear(); renderDashCart();">Clear Cart</button>
            </div>
        </div>
        <div class="notice-box-inline mt-2">
            <strong><span style="font-size:1.1rem; vertical-align:middle; margin-right:5px;">⚠️</span> IMPORTANT</strong>
            <span style="color:var(--light);">This order is configured for <b>IN-PERSON PAYMENT</b>. Please pay the seller directly upon collection.</span>
        </div>
        <button class="btn btn-checkout-lg mt-2" style="width:100%;" onclick="window.location.href='checkout.php?ids=' + cmCart.get().map(i => i.id).join(',');">Proceed to Checkout</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.renderDashCart = function() {
        if(typeof cmCart === 'undefined') return;
        const cart = cmCart.get();
        const container = document.getElementById('dash-cart-items');
        const empty = document.getElementById('dash-cart-empty');
        const footer = document.getElementById('dash-cart-footer');
        
        if (cart.length === 0) {
            container.innerHTML = '';
            empty.style.display = 'block';
            footer.style.display = 'none';
            return;
        }
        
        empty.style.display = 'none';
        footer.style.display = 'block';
        
        container.innerHTML = cart.map(item => `
            <div style="display:grid; grid-template-columns:50px 1fr auto; gap:1rem; align-items:center; padding:1rem; background:rgba(0,0,0,0.2); border-radius:12px; border:1px solid var(--border);">
                <img src="${item.image || ''}" alt="${item.name}" onerror="this.style.background='rgba(0,0,0,0.3)'; this.alt='No Image';" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">
                <div>
                    <p style="font-weight:600; margin-bottom:4px; font-size:0.9rem;">${item.name}</p>
                    <p style="color:var(--primary); font-weight:700; font-size:0.95rem;">₵${(item.price * item.qty).toFixed(2)}</p>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="cmCart.updateQty(${item.id}, ${item.qty - 1}); renderDashCart();">−</button>
                    <span style="font-weight:600;font-size:0.9rem;">${item.qty}</span>
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="cmCart.updateQty(${item.id}, ${item.qty + 1}); renderDashCart();">+</button>
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px; color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="cmCart.remove(${item.id}); renderDashCart();">✕</button>
                </div>
            </div>
        `).join('');
        
        document.getElementById('dash-cart-total').textContent = '₵' + cmCart.total().toFixed(2);
    };
    renderDashCart();
});
</script>
<?php endif; ?>

<!-- ADDED: Toast notification -->
<div class="toast-notify" id="dashToast"></div>

<!-- ADDED: Product row selection & discount submission JS -->
<script>
let selectedPid = null;

function selectProductRow(pid) {
    // Deselect previous
    document.querySelectorAll('.product-row').forEach(r => r.classList.remove('selected'));
    document.querySelectorAll('[id^="submit-area-"]').forEach(a => a.style.display = 'none');

    if (selectedPid === pid) { selectedPid = null; return; }
    selectedPid = pid;

    const row = document.getElementById('prow-' + pid);
    const area = document.getElementById('submit-area-' + pid);
    if (row) row.classList.add('selected');
    if (area) area.style.display = 'block';
}

function submitDiscount(pid) {
    const input = document.getElementById('disc-' + pid);
    const val = parseInt(input?.value) || 0;
    if (val < 1 || val > 90) {
        showDashToast('⚠️ Enter a valid discount between 1% and 90%');
        input?.focus();
        return;
    }
    window.location.href = '?action=submit_discount&pid=' + pid + '&disc=' + val;
}

function showDashToast(msg) {
    const t = document.getElementById('dashToast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('visible');
    setTimeout(() => t.classList.remove('visible'), 2800);
}
</script>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payWithPaystack(tier, amount) {
    const handler = PaystackPop.setup({
        key: '<?= get_env_var("PAYSTACK_PUBLIC_KEY") ?>',
        email: '<?= $user["email"] ?>',
        amount: amount * 100, // in kobo/pesewas
        currency: 'GHS',
        metadata: { tier: tier, user_id: '<?= $user["id"] ?>' },
        callback: function(response) {
            // Verify Transaction on Backend
            verifyPayment(response.reference, tier);
        },
        onClose: function() {
            alert('Transaction was not completed.');
        }
    });
    handler.openIframe();
}

async function verifyPayment(reference, tier) {
    try {
        const res = await fetch('api/paystack_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reference: reference, tier: tier })
        });
        const data = await res.json();
        if(data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch(e) {
        alert('CRITICAL PAYMENT ERROR: Could not verify transaction.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
