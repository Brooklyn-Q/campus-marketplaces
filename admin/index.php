<?php
$page_title = 'Dashboard';
require_once 'header.php';

// Add missing message column to announcements if it doesn't exist (Supabase transition)
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS message TEXT"); } catch(PDOException $e) {}

$disc_msg = '';
if (isset($_GET['disc_action']) && isset($_GET['disc_id'])) {
    $disc_id = (int)$_GET['disc_id'];
    $disc_act = $_GET['disc_action'];
    
    if ($disc_act === 'approve_discount') {
        $pdo->beginTransaction();
        try {
            $req = $pdo->prepare("SELECT * FROM discount_requests WHERE id = ? AND status = 'pending'");
            $req->execute([$disc_id]);
            $dr = $req->fetch(PDO::FETCH_ASSOC);
            if ($dr) {
                // FIXED: Update discount_requests status
                $pdo->prepare("UPDATE discount_requests SET status = 'approved' WHERE id = ?")->execute([$disc_id]);
                // FIXED: Apply discounted price to the product globally
                $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$dr['discounted_price'], $dr['product_id']]);
                auditLog($pdo, $_SESSION['user_id'], "Approved discount #{$disc_id} for product #{$dr['product_id']}", 'discount', $disc_id);
                $disc_msg = "✅ Discount approved! Product price updated to ₵" . number_format($dr['discounted_price'], 2);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $disc_msg = "❌ Error approving discount: " . $e->getMessage();
        }
    } elseif ($disc_act === 'reject_discount') {
        $pdo->prepare("UPDATE discount_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'")->execute([$disc_id]);
        auditLog($pdo, $_SESSION['user_id'], "Rejected discount #{$disc_id}", 'discount', $disc_id);
        $disc_msg = "❌ Discount request rejected.";
    }
}

// Handle profile edit approval/rejection
$profile_msg = '';
if (isset($_GET['profile_action']) && isset($_GET['req_id'])) {
    $req_id = (int)$_GET['req_id'];
    $p_act = $_GET['profile_action'];

    if ($p_act === 'approve_profile') {
        try {
            $req = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE id = ? AND status = 'pending'");
            $req->execute([$req_id]);
            $pr = $req->fetch(PDO::FETCH_ASSOC);
            if ($pr) {
                // Apply the change to the user
                $safe_fields = ['bio','department','level','hall','phone','faculty','whatsapp','instagram','linkedin','seller_tier'];
                if (in_array($pr['field_name'], $safe_fields)) {
                    if ($pr['field_name'] === 'seller_tier' && $pr['new_value'] === 'basic') {
                        $pdo->prepare("UPDATE users SET seller_tier = 'basic', tier_expires_at = NULL WHERE id = ?")->execute([$pr['user_id']]);
                    } else {
                        $pdo->prepare("UPDATE users SET `{$pr['field_name']}` = ? WHERE id = ?")->execute([$pr['new_value'], $pr['user_id']]);
                    }
                }
                $pdo->prepare("UPDATE profile_edit_requests SET status='approved', admin_id=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $req_id]);
                auditLog($pdo, $_SESSION['user_id'], "Approved profile edit #{$req_id} for user #{$pr['user_id']}", 'profile', $req_id);
                $profile_msg = "✅ Profile change approved and applied.";
            }
        } catch (Exception $e) {
            $profile_msg = "❌ Error: " . $e->getMessage();
        }
    } elseif ($p_act === 'reject_profile') {
        $pdo->prepare("UPDATE profile_edit_requests SET status='rejected', admin_id=?, resolved_at=NOW() WHERE id=? AND status='pending'")->execute([$_SESSION['user_id'], $req_id]);
        auditLog($pdo, $_SESSION['user_id'], "Rejected profile edit #{$req_id}", 'profile', $req_id);
        $profile_msg = "❌ Profile change rejected.";
    } elseif ($p_act === 'approve_all_user') {
        $uid = (int)$_GET['uid'];
        $reqs = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE user_id=? AND status='pending'");
        $reqs->execute([$uid]);
        $all_reqs = $reqs->fetchAll(PDO::FETCH_ASSOC);
        $safe_fields = ['bio','department','level','hall','phone','faculty','whatsapp','instagram','linkedin'];
        foreach ($all_reqs as $pr) {
            if (in_array($pr['field_name'], $safe_fields)) {
                $pdo->prepare("UPDATE users SET `{$pr['field_name']}` = ? WHERE id = ?")->execute([$pr['new_value'], $pr['user_id']]);
            }
            $pdo->prepare("UPDATE profile_edit_requests SET status='approved', admin_id=?, resolved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $pr['id']]);
        }
        auditLog($pdo, $_SESSION['user_id'], "Bulk approved all profile edits for user #{$uid}", 'profile', $uid);
        $profile_msg = "✅ All profile changes for user #{$uid} approved.";
    }
}

// ── DATA FETCHING (Consolidated at top for safety) ──

// Stats
$stats = [
    'total_users'   => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
    'sellers'       => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'seller'")->fetchColumn(),
    'buyers'        => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn(),
    'active'        => $pdo->query("SELECT COUNT(*) FROM products WHERE status='approved'")->fetchColumn(),
    'pending'       => $pdo->query("SELECT COUNT(*) FROM products WHERE status='pending'")->fetchColumn(),
    'deletion_req'  => $pdo->query("SELECT COUNT(*) FROM products WHERE status='deletion_requested'")->fetchColumn(),
    'total_tx'      => $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'volume'        => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed'")->fetchColumn(),
    'premium_rev'   => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='premium' AND status='completed'")->fetchColumn(),
    'boost_rev'     => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='boost' AND status='completed'")->fetchColumn(),
    'sale_rev'      => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='sale' AND status='completed'")->fetchColumn(),
    'total_orders'  => 0,
    'disc_pending'  => 0,
    'profile_pending' => 0
];
try { $stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); } catch(Exception $e) {}

// Current Prices
$currentPrePrice = getSetting($pdo, 'premium_price', '10');
$currentBstPrice = getSetting($pdo, 'ad_boost_price', '5');

// Pending discount requests
$discountPending = [];
try {
    $dstmt = $pdo->query("SELECT dr.*, p.title AS product_name, u.username AS seller_name 
        FROM discount_requests dr 
        JOIN products p ON dr.product_id = p.id 
        JOIN users u ON dr.seller_id = u.id 
        WHERE dr.status = 'pending' ORDER BY dr.created_at DESC");
    if ($dstmt) $discountPending = $dstmt->fetchAll();
    $stats['disc_pending'] = count($discountPending);
} catch(PDOException $e) { /* ignore */ }

// Pending premium requests (DE-DUPLICATED: Only shows the latest request from each unique user)
$premiumPending = [];
try {
    $pstmt = $pdo->query("SELECT m.*, u.username as sender_name, u.seller_tier 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = 1 
        AND m.message LIKE '%Premium Badge upgrade%' 
        AND m.id IN (SELECT MAX(id) FROM messages WHERE message LIKE '%Premium Badge upgrade%' GROUP BY sender_id)
        ORDER BY m.created_at DESC");
    $premiumPending = $pstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) { /* ignore */ }

// Pending profile edit requests
$profileByUser = [];
try {
    $pe_stmt = $pdo->query("SELECT per.*, u.username, u.profile_pic FROM profile_edit_requests per JOIN users u ON per.user_id = u.id WHERE per.status='pending' ORDER BY per.user_id, per.created_at DESC");
    $all_profiles = $pe_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($all_profiles as $pe) {
        if (!isset($profileByUser[$pe['user_id']])) {
            $profileByUser[$pe['user_id']] = ['username'=>$pe['username'], 'profile_pic'=>$pe['profile_pic'], 'edits'=>[]];
        }
        $profileByUser[$pe['user_id']]['edits'][] = $pe;
    }
    $stats['profile_pending'] = count($profileByUser);
} catch(PDOException $e) { /* ignore */ }

$topSellers = $pdo->query("SELECT u.username, u.seller_tier, COALESCE(SUM(t.amount),0) as revenue
    FROM users u LEFT JOIN transactions t ON u.id = t.user_id AND t.type = 'sale'
    WHERE u.role = 'seller' GROUP BY u.id ORDER BY revenue DESC LIMIT 5")->fetchAll();

$pending = $pdo->query("SELECT p.*, u.username as seller FROM products p JOIN users u ON p.user_id = u.id WHERE p.status='pending' ORDER BY p.created_at ASC LIMIT 5")->fetchAll();

// Table migrations for disputes and discount_requests are now in migrate.php


// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tiers'])) {
    check_csrf();
    $tiers = ['basic', 'pro', 'premium'];
    foreach($tiers as $t) {
        $limit = max(0, (int)$_POST["{$t}_product_limit"]);
        $img = max(1, (int)$_POST["{$t}_image_limit"]);
        $price = max(0, (float)$_POST["{$t}_fee"]);
        $dur = max(1, (int)($_POST["{$t}_duration"] ?? 1));
        $badge = $_POST["badge_color_{$t}"] ?? 'blue';
        $ads = isset($_POST["{$t}_ads_boost"]) ? 1 : 0;
        
        $benefits = isset($_POST["{$t}_benefits"]) && is_array($_POST["{$t}_benefits"]) ? $_POST["{$t}_benefits"] : [];
        $benefits = array_filter(array_map('trim', $benefits));
        $benefits_json = json_encode(array_values($benefits));
        
        $pdo->prepare("UPDATE account_tiers SET product_limit=?, images_per_product=?, price=?, duration=?, badge=?, ads_boost=?, benefits=? WHERE tier_name=?")
            ->execute([$limit, $img, $price, $dur, $badge, $ads, $benefits_json, $t]);
    }
    auditLog($pdo, $_SESSION['user_id'], "Updated marketplace tier settings in account_tiers");
    $disc_msg = "✅ Tier configurations globally updated!";
}

// Handle vacation requests
if (isset($_GET['vacation_action']) && isset($_GET['vac_id'])) {
    $vac_id = (int)$_GET['vac_id'];
    $vac_act = $_GET['vacation_action'];
    if ($vac_act === 'approve') {
        $req = $pdo->prepare("SELECT seller_id FROM vacation_requests WHERE id = ?");
        $req->execute([$vac_id]);
        $vr = $req->fetch();
        if ($vr) {
            $pdo->prepare("UPDATE users SET vacation_mode = 1 WHERE id = ?")->execute([$vr['seller_id']]);
            $pdo->prepare("UPDATE vacation_requests SET status = 'approved' WHERE id = ?")->execute([$vac_id]);
            auditLog($pdo, $_SESSION['user_id'], "Approved vacation for seller #{$vr['seller_id']}");
            $disc_msg = "✅ Vacation mode approved for seller.";
        }
    } elseif ($vac_act === 'reject') {
        $pdo->prepare("UPDATE vacation_requests SET status = 'rejected' WHERE id = ?")->execute([$vac_id]);
        $disc_msg = "❌ Vacation mode rejected.";
    }
}

// Handle global announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    check_csrf();
    $msg_content = trim($_POST['announcement_msg'] ?? '');
    $ann_type = $_POST['ann_type'] ?? 'info';
    if ($msg_content) {
        $pdo->prepare("INSERT INTO announcements (admin_id, message, type) VALUES (?,?,?)")
            ->execute([$_SESSION['user_id'], $msg_content, $ann_type]);
        $disc_msg = "✅ Global announcement published successfully!";
        // Assuming auditLog function exists
        if(function_exists('auditLog')) auditLog($pdo, $_SESSION['user_id'], "Published global announcement: " . substr($msg_content, 0, 30) . "...");
    }
}
if (isset($_GET['ann_action']) && isset($_GET['ann_id'])) {
    $ann_id = (int)$_GET['ann_id'];
    if ($_GET['ann_action'] === 'deactivate') {
        $boolF = sqlBool(false, $pdo);
    $pdo->prepare("UPDATE announcements SET is_active = $boolF WHERE id = ?")->execute([$ann_id]);
        $disc_msg = "✅ Announcement deactivated.";
    } elseif ($_GET['ann_action'] === 'delete') {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$ann_id]);
        $disc_msg = "✅ Announcement deleted from history.";
    }
}

// Fetch current settings for the form
$s_map = [];
$settings_all = $pdo->query("SELECT * FROM settings")->fetchAll();
foreach ($settings_all as $s) { $s_map[$s['setting_key']] = $s['setting_value']; }

function getS(array $map, string $key, $def = ''): string {
    return $map[$key] ?? (string)$def;
}
?>

<?php
$aTiers = getAccountTiers($pdo);
$tb = $aTiers['basic'] ?? ['product_limit'=>2, 'images_per_product'=>1, 'badge'=>'#0071e3', 'duration'=>'forever', 'price'=>0, 'ads_boost'=>0];
$tp = $aTiers['pro'] ?? ['product_limit'=>5, 'images_per_product'=>1, 'badge'=>'#8e8e93', 'duration'=>'2_weeks', 'price'=>10, 'ads_boost'=>0];
$tm = $aTiers['premium'] ?? ['product_limit'=>15, 'images_per_product'=>3, 'badge'=>'#ff9f0a', 'duration'=>'weekly', 'price'=>20, 'ads_boost'=>1];
?>
<!-- TIER & PRICING MANAGEMENT SYSTEM -->
<div class="glass fade-in mb-3" style="padding:1.5rem; border:1px solid var(--gold);">
    <h4 class="mb-2">⚙️ Tier & Pricing Management</h4>
    <form method="POST">
        <?= csrf_field() ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Basic -->
            <div style="background:rgba(0,113,227,0.05); padding:1rem; border-radius:16px; border:1px solid rgba(0,113,227,0.1);">
                <h5 style="color:#0071e3; margin-bottom:1rem; display:flex; justify-content:space-between;">Basic Tier <span style="font-size:0.7rem; opacity:0.7;">Free</span></h5>
                <input type="hidden" name="basic_fee" value="0">
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Duration (Months)</label>
                    <input type="number" name="basic_duration" class="form-control" value="<?= htmlspecialchars($tb['duration']) ?>" min="1">
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Product Limit</label>
                    <input type="number" name="basic_product_limit" class="form-control" value="<?= $tb['product_limit'] ?>">
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Images Per Product</label>
                    <input type="number" name="basic_image_limit" class="form-control" value="<?= $tb['images_per_product'] ?>">
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Badge Color</label>
                    <input type="color" name="badge_color_basic" class="form-control" value="<?= htmlspecialchars($tb['badge']) ?>" style="height:35px; padding:2px;">
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem; font-weight:700;">Included Benefits</label>
                    <div id="benefits_wrap_basic" style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.5rem; min-height:28px;">
                        <?php foreach(json_decode($tb['benefits'] ?? '[]', true) ?: [] as $ben): ?>
                            <div class="badge" style="background:#0071e320; color:#0071e3; display:flex; align-items:center; gap:4px; padding:4px 8px;">
                                <?= htmlspecialchars($ben) ?>
                                <input type="hidden" name="basic_benefits[]" value="<?= htmlspecialchars($ben) ?>">
                                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:bold; padding:0;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex; gap:0.4rem;">
                        <input type="text" id="add_ben_basic" class="form-control" style="font-size:0.75rem; padding:0.4rem;" placeholder="E.g. Priority Support" onkeypress="if(event.key==='Enter'){ event.preventDefault(); addBenefit('basic', '#0071e3'); }">
                        <button type="button" class="btn btn-sm btn-outline" onclick="addBenefit('basic', '#0071e3')">+</button>
                    </div>
                </div>
            </div>

            <!-- Pro -->
            <div style="background:rgba(142,142,147,0.05); padding:1rem; border-radius:16px; border:1px solid rgba(142,142,147,0.1);">
                <h5 style="color:#8e8e93; margin-bottom:1rem;">Pro Tier</h5>
                <div class="grid-2-cols" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Limit</label>
                        <input type="number" name="pro_product_limit" class="form-control" value="<?= $tp['product_limit'] ?>">
                    </div>
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Images</label>
                        <input type="number" name="pro_image_limit" class="form-control" value="<?= $tp['images_per_product'] ?>">
                    </div>
                </div>
                <div class="grid-2-cols" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Fee (₵)</label>
                        <input type="number" name="pro_fee" class="form-control" value="<?= $tp['price'] ?>">
                    </div>
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Duration (Months)</label>
                        <input type="number" name="pro_duration" class="form-control" value="<?= htmlspecialchars($tp['duration']) ?>" min="1">
                    </div>
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Badge Color</label>
                    <input type="color" name="badge_color_pro" class="form-control" value="<?= htmlspecialchars($tp['badge']) ?>" style="height:35px; padding:2px;">
                </div>
                <!-- Pro Benefits -->
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem; font-weight:700;">Included Benefits</label>
                    <div id="benefits_wrap_pro" style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.5rem; min-height:28px;">
                        <?php foreach(json_decode($tp['benefits'] ?? '[]', true) ?: [] as $ben): ?>
                            <div class="badge" style="background:#8e8e9320; color:#8e8e93; display:flex; align-items:center; gap:4px; padding:4px 8px;">
                                <?= htmlspecialchars($ben) ?>
                                <input type="hidden" name="pro_benefits[]" value="<?= htmlspecialchars($ben) ?>">
                                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:bold; padding:0;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex; gap:0.4rem;">
                        <input type="text" id="add_ben_pro" class="form-control" style="font-size:0.75rem; padding:0.4rem;" placeholder="E.g. Analytics Dashboard" onkeypress="if(event.key==='Enter'){ event.preventDefault(); addBenefit('pro', '#8e8e93'); }">
                        <button type="button" class="btn btn-sm btn-outline" onclick="addBenefit('pro', '#8e8e93')">+</button>
                    </div>
                </div>
            </div>

            <!-- Premium -->
            <div style="background:rgba(255,159,10,0.05); padding:1rem; border-radius:16px; border:1px solid rgba(255,159,10,0.12);">
                <h5 style="color:#ff9f0a; margin-bottom:1rem;">Premium Tier</h5>
                <div class="grid-2-cols" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Limit</label>
                        <input type="number" name="premium_product_limit" class="form-control" value="<?= $tm['product_limit'] ?>">
                    </div>
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Images</label>
                        <input type="number" name="premium_image_limit" class="form-control" value="<?= $tm['images_per_product'] ?>">
                    </div>
                </div>
                <div class="grid-2-cols" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Fee (₵)</label>
                        <input type="number" name="premium_fee" class="form-control" value="<?= $tm['price'] ?>">
                    </div>
                    <div class="form-group mb-1">
                        <label style="font-size:0.75rem;">Duration (Months)</label>
                        <input type="number" name="premium_duration" class="form-control" value="<?= htmlspecialchars($tm['duration']) ?>" min="1">
                    </div>
                </div>
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem;">Badge Color</label>
                    <input type="color" name="badge_color_premium" class="form-control" value="<?= htmlspecialchars($tm['badge']) ?>" style="height:35px; padding:2px;">
                </div>
                <!-- Premium Benefits -->
                <div class="form-group mb-1">
                    <label style="font-size:0.75rem; font-weight:700;">Included Benefits</label>
                    <div id="benefits_wrap_premium" style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.5rem; min-height:28px;">
                        <?php foreach(json_decode($tm['benefits'] ?? '[]', true) ?: [] as $ben): ?>
                            <div class="badge" style="background:#ff9f0a20; color:#ff9f0a; display:flex; align-items:center; gap:4px; padding:4px 8px;">
                                <?= htmlspecialchars($ben) ?>
                                <input type="hidden" name="premium_benefits[]" value="<?= htmlspecialchars($ben) ?>">
                                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:bold; padding:0;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex; gap:0.4rem;">
                        <input type="text" id="add_ben_premium" class="form-control" style="font-size:0.75rem; padding:0.4rem;" placeholder="E.g. Ad Boost Access" onkeypress="if(event.key==='Enter'){ event.preventDefault(); addBenefit('premium', '#ff9f0a'); }">
                        <button type="button" class="btn btn-sm btn-outline" onclick="addBenefit('premium', '#ff9f0a')">+</button>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" name="update_tiers" class="btn btn-primary" style="width:100%; border-radius:14px; font-weight:700;">Save All Tier Changes</button>
    </form>
</div>

<script>
function addBenefit(tier, colorHex) {
    const input = document.getElementById('add_ben_' + tier);
    const val = input.value.trim();
    if (!val) return;
    
    // Prevent duplicate benefits client-side
    const wrap = document.getElementById('benefits_wrap_' + tier);
    const existings = wrap.querySelectorAll('input[type="hidden"]');
    for (let eq of existings) {
        if (eq.value.toLowerCase() === val.toLowerCase()) {
            input.value = '';
            return;
        }
    }
    
    // Create new chip element
    const div = document.createElement('div');
    div.className = 'badge';
    div.style.cssText = `background:${colorHex}20; color:${colorHex}; display:flex; align-items:center; gap:4px; padding:4px 8px;`;
    
    const textNode = document.createTextNode(val + " ");
    div.appendChild(textNode);
    
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = tier + '_benefits[]';
    hidden.value = val;
    div.appendChild(hidden);
    
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = '&times;';
    btn.style.cssText = 'background:none; border:none; color:inherit; cursor:pointer; font-weight:bold; padding:0;';
    btn.onclick = function() { div.remove(); };
    div.appendChild(btn);
    
    wrap.appendChild(div);
    input.value = '';
}
</script>

<!-- GLOBAL ANNOUNCEMENT SYSTEM -->
<div class="glass fade-in mb-4" style="padding:2rem; border-radius:24px; border:1px solid #10b981; background:rgba(16,185,129,0.05);">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:1rem;">
        <span style="font-size:1.8rem;">📢</span>
        <h4 style="margin:0; font-size:1.5rem; font-weight:800;">Global Broadcast (Announcements)</h4>
    </div>
    <p class="text-muted" style="font-size:0.9rem; margin-bottom:1.5rem;">Blast an update that will appear at the top of every dashboard (Seller & Buyer). <b>Use with power!</b></p>
    
    <form method="POST" class="mb-4">
        <?= csrf_field() ?>
        <div style="display:grid; grid-template-columns: 1fr 200px auto; gap:1rem; align-items:flex-end;" class="grid-1-col-mobile">
            <div>
                <label style="font-size:0.8rem; font-weight:700; color:var(--text-main); display:block; margin-bottom:0.5rem;">Broadcast Message</label>
                <textarea name="announcement_msg" class="form-control" placeholder="Type your site-wide update here (e.g., maintenance, new policy, feature update)..." style="min-height:100px; padding:1rem; border-radius:16px; font-size:0.95rem;" required></textarea>
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:700; color:var(--text-main); display:block; margin-bottom:0.5rem;">Alert Style</label>
                <select name="ann_type" class="form-control" style="border-radius:12px; padding:0.8rem;">
                    <option value="info">🔵 Information (Blue)</option>
                    <option value="warning">🟠 Warning (Orange)</option>
                    <option value="success">🟢 Success/Update (Green)</option>
                    <option value="danger">🔴 Urgent/Broken (Red)</option>
                </select>
            </div>
            <button type="submit" name="send_announcement" class="btn btn-primary" style="padding:1rem 2.5rem; border-radius:16px; font-weight:800; font-size:1rem; box-shadow:0 10px 20px rgba(16,185,129,0.2);">🚀 Send Update</button>
        </div>
    </form>

    <div>
        <h5 style="font-size:0.95rem; margin-bottom:1rem; font-weight:700;">Currently Active Updates</h5>
        <?php
        $boolT = sqlBool(true, $pdo);
    $activeAnn = $pdo->query("SELECT * FROM announcements WHERE is_active = $boolT ORDER BY created_at DESC LIMIT 5")->fetchAll();
        ?>
        <?php if(count($activeAnn) > 0): ?>
            <div style="display:grid; gap:0.75rem;">
                <?php foreach($activeAnn as $a): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.1); padding:1rem; border-radius:16px; border:1px solid rgba(255,255,255,0.05);">
                        <div style="font-size:0.9rem; flex:1;">
                            <span class="badge badge-<?= $a['type'] ?>" style="font-size:0.7rem; margin-right:12px; padding:4px 10px;"><?= strtoupper($a['type']) ?></span>
                            <?= htmlspecialchars($a['message']) ?>
                            <small style="display:block; font-size:0.7rem; color:var(--text-muted); margin-top:4px;">Published: <?= date('M d, H:i', strtotime($a['created_at'])) ?></small>
                        </div>
                        <a href="?ann_action=deactivate&ann_id=<?= $a['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem; border-color:rgba(255,59,48,0.2); color:#ff3b30; padding:6px 14px;">Deactivate</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-center" style="padding:2rem; background:rgba(0,0,0,0.05); border-radius:16px; border:1px dashed var(--border);">No current broadcasts. Site is quiet.</p>
        <?php endif; ?>
    </div>
</div>

<h2 class="mb-3">Admin Dashboard</h2>

<?php if($disc_msg): ?><div class="alert <?= strpos($disc_msg, '✅') !== false ? 'alert-success' : 'alert-error' ?> fade-in"><?= htmlspecialchars($disc_msg) ?></div><?php endif; ?>
<?php if($profile_msg): ?><div class="alert <?= strpos($profile_msg, '✅') !== false ? 'alert-success' : 'alert-error' ?> fade-in"><?= htmlspecialchars($profile_msg) ?></div><?php endif; ?>

<?php if($stats['profile_pending'] > 0): ?>
<style>
/* FULL-SCREEN ADMIN DASHBOARD */
.container { 
    max-width: none !important; 
    width: 96% !important; 
    padding-left: 2rem !important; 
    padding-right: 2rem !important; 
}
</style>

<div class="container fade-in" style="background:rgba(168,85,247,0.1); border:1px solid rgba(168,85,247,0.2); color:#9333ea; display:flex; align-items:center; justify-content:space-between;">
    <div><strong>🔔 Profile & Tier Changes Pending</strong> — <?= $stats['profile_pending'] ?> user(s) have requested updates to their accounts.</div>
    <a href="#profile_section" class="btn btn-sm" style="background:#a855f7; color:#fff;">Review Now</a>
</div>
<?php endif; ?>



<div class="stat-grid mb-3 fade-in">
    <a href="users.php?filter=all" class="stat-card-link"><div class="glass stat-card"><div class="stat-val"><?= $stats['total_users'] ?></div><div class="stat-label">Total Users</div></div></a>
    <a href="users.php?filter=sellers" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $stats['sellers'] ?></div><div class="stat-label">Sellers</div></div></a>
    <a href="products.php?filter=approved" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);"><?= $stats['active'] ?></div><div class="stat-label">Active Listings</div></div></a>
    <a href="products.php?filter=pending" class="stat-card-link"><div class="glass stat-card" style="<?= $stats['pending'] > 0 ? 'border-color:#fbbf24;' : '' ?>"><div class="stat-val" style="color:var(--warning);"><?= $stats['pending'] ?></div><div class="stat-label">Pending Review</div></div></a>
    <a href="products.php?filter=deletion_requested" class="stat-card-link"><div class="glass stat-card" style="<?= $stats['deletion_req'] > 0 ? 'border-color:var(--danger);' : '' ?>"><div class="stat-val" style="color:var(--danger);"><?= $stats['deletion_req'] ?></div><div class="stat-label">Deletion Requests</div></div></a>
    <!-- ADDED: Discount pending stat card -->
    <a href="#discount_section" class="stat-card-link"><div class="glass stat-card" style="<?= $stats['disc_pending'] > 0 ? 'border-color:var(--mint);' : '' ?>"><div class="stat-val" style="color:var(--mint);"><?= $stats['disc_pending'] ?></div><div class="stat-label">Discount Requests</div></div></a>
    <!-- ADDED: Profile edit pending stat card -->
    <a href="#profile_section" class="stat-card-link"><div class="glass stat-card" style="<?= $stats['profile_pending'] > 0 ? 'border-color:#a855f7;' : '' ?>"><div class="stat-val" style="color:#a855f7;"><?= $stats['profile_pending'] ?></div><div class="stat-label">Profile Edits</div></div></a>
    <a href="#" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">₵<?= number_format($stats['volume'], 2) ?></div><div class="stat-label">Total Revenue</div></div></a>
    <a href="#" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:var(--success);">₵<?= number_format($stats['sale_rev'], 2) ?></div><div class="stat-label">Product Sales</div></div></a>
    <a href="#" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:#af52de;">₵<?= number_format($stats['premium_rev'], 2) ?></div><div class="stat-label">Premium Fees</div></div></a>
    <a href="#" class="stat-card-link"><div class="glass stat-card"><div class="stat-val" style="color:#ff9f0a;">₵<?= number_format($stats['boost_rev'], 2) ?></div><div class="stat-label">Boost Revenue</div></div></a>
    <a href="#transparency_section" class="stat-card-link"><div class="glass stat-card"><div class="stat-val"><?= $stats['total_orders'] ?></div><div class="stat-label">Total Orders</div></div></a>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
    <!-- Top Sellers -->
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2">🏆 Top Sellers Leaderboard</h4>
        <table>
            <thead><tr><th>#</th><th>Seller</th><th>Tier</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php foreach($topSellers as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['username']) ?></td>
                    <td><span class="badge <?= $s['seller_tier']==='premium' ? 'badge-gold' : 'badge-blue' ?>"><?= ucfirst($s['seller_tier']) ?></span></td>
                    <td>₵<?= number_format($s['revenue'], 2) ?></td>
                    <td>
                    <?php 
                        $seller_user = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $seller_user->execute([$s['username']]);
                        $seller_uid = $seller_user->fetchColumn();
                    ?>
                    <a href="../chat.php?user=<?= $seller_uid ?>" class="btn btn-outline btn-sm">💬</a></td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <!-- Pending -->
    <div class="glass fade-in" style="padding:1.5rem;">
        <div class="flex-between mb-2">
            <h4>⏳ Pending Items</h4>
            <a href="products.php?filter=pending" class="btn btn-primary btn-sm">View All</a>
        </div>
        <?php if(count($pending) > 0): ?>
            <table>
                <thead><tr><th>Title</th><th>Seller</th><th>Quick</th></tr></thead>
                <tbody>
                    <?php foreach($pending as $p): ?>
                    <tr>
                        <td><a href="../product.php?id=<?= $p['id'] ?>" target="_blank" style="color:var(--primary);"><?= htmlspecialchars($p['title']) ?></a></td>
                        <td><?= htmlspecialchars($p['seller']) ?></td>
                        <td>
                            <a href="products.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-success btn-sm">✓</a>
                            <a href="products.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm">✗</a>
                            <a href="../chat.php?user=<?= $p['user_id'] ?>" class="btn btn-outline btn-sm">💬</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">All clear! Nothing pending.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Pending Vacation Requests Section -->
<div id="vacation_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>🏝️ Pending Vacation Requests</h4>
        <span class="badge <?= $pdo->query("SELECT COUNT(*) FROM vacation_requests WHERE status='pending'")->fetchColumn() > 0 ? 'badge-pending' : 'badge-approved' ?>">Action Required</span>
    </div>
    <?php
    $vacPending = $pdo->query("SELECT v.*, u.username FROM vacation_requests v JOIN users u ON v.seller_id = u.id WHERE v.status='pending' ORDER BY v.created_at DESC")->fetchAll();
    ?>
    <?php if(count($vacPending) > 0): ?>
        <table>
            <thead><tr><th>Seller</th><th>Requested On</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($vacPending as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['username']) ?></strong></td>
                    <td><?= date('M d, H:i', strtotime($v['created_at'])) ?></td>
                    <td><span class="badge badge-pending">PENDING</span></td>
                    <td>
                        <a href="?vacation_action=approve&vac_id=<?= $v['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve vacation mode for this seller?')">Approve</a>
                        <a href="?vacation_action=reject&vac_id=<?= $v['id'] ?>" class="btn btn-danger btn-sm">Reject</a>
                        <a href="../chat.php?user=<?= $v['seller_id'] ?>" class="btn btn-outline btn-sm">💬 Chat</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted text-center" style="padding:1rem;">No pending vacation requests. Sellers are working hard!</p>
    <?php endif; ?>
</div>


<!-- Order Transparency Hub Section -->
<div id="transparency_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>📦 Marketplace Transparency Hub (All Orders)</h4>
        <a href="audit.php" class="btn btn-outline btn-sm">View Audit Logs</a>
    </div>

    <?php
    $allOrders = $pdo->query("
        SELECT o.*, p.title as prod_name, b.username as buyer_name, s.username as seller_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        JOIN users b ON o.buyer_id = b.id 
        JOIN users s ON o.seller_id = s.id 
        ORDER BY o.created_at DESC LIMIT 50
    ")->fetchAll();
    ?>

    <?php if(count($allOrders) > 0): ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; font-size:0.85rem; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--border);">
                        <th style="padding:0.75rem;">ID</th>
                        <th style="padding:0.75rem;">Product</th>
                        <th style="padding:0.75rem;">Participants</th>
                        <th style="padding:0.75rem;">Status</th>
                        <th style="padding:0.75rem;">Timeline</th>
                        <th style="padding:0.75rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allOrders as $o): ?>
                    <tr style="border-bottom:1px solid rgba(0,0,0,0.02);">
                        <td style="padding:0.75rem;">#<?= $o['id'] ?></td>
                        <td style="padding:0.75rem;">
                            <strong><?= htmlspecialchars($o['prod_name']) ?></strong><br>
                            <span class="text-muted">₵<?= number_format($o['price'],2) ?></span>
                        </td>
                        <td style="padding:0.75rem;">
                            B: <?= htmlspecialchars($o['buyer_name']) ?><br>
                            S: <?= htmlspecialchars($o['seller_name']) ?>
                        </td>
                        <td style="padding:0.75rem;">
                            <?php 
                                $st = $o['status'];
                                $lbl = 'Pending';
                                $cls = 'badge-pending';
                                if($st === 'seller_seen') { $lbl = 'Seller Replied'; $cls = 'badge-pending'; }
                                if($o['seller_confirmed']) { $lbl = 'Sold (Seller Confirmed)'; $cls = 'badge-approved'; }
                                if($o['buyer_confirmed']) { $lbl = 'Received (Buyer Confirmed)'; $cls = 'badge-approved'; }
                                if($st === 'completed') { $lbl = 'Completed'; $cls = 'badge-approved'; }
                            ?>
                            <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                        </td>
                        <td style="padding:0.75rem;">
                            <div style="font-size:0.72rem; color:var(--text-muted);">
                            Ordered: <?= date('M d, H:i', strtotime($o['created_at'])) ?><br>
                            Last Update: <?= $o['updated_at'] ? date('M d, H:i', strtotime($o['updated_at'])) : 'N/A' ?>
                            </div>
                        </td>
                        <td style="padding:0.75rem;">
                            <a href="messages.php?view=chat&u1=<?= $o['buyer_id'] ?>&u2=<?= $o['seller_id'] ?>" class="btn btn-sm" style="font-size:0.7rem; background:rgba(0,113,227,0.1); color:#0071e3;">View Chat Log</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-center" style="padding:2rem;">No orders tracked yet.</p>
    <?php endif; ?>
</div>

<!-- Conversation Monitor Link Card -->
<div class="stat-grid mt-3">
    <a href="messages.php" class="stat-card-link">
        <div class="glass stat-card" style="padding:1.5rem; text-align:center;">
            <div style="font-size:1.5rem;">💬</div>
            <h5 class="mt-2">Chat Surveillance</h5>
            <p class="text-muted" style="font-size:0.75rem;">Monitor all buyer-seller interactions and logs.</p>
        </div>
    </a>
    <a href="audit.php" class="stat-card-link">
        <div class="glass stat-card" style="padding:1.5rem; text-align:center;">
            <div style="font-size:1.5rem;">📋</div>
            <h5 class="mt-2">Transaction Audit</h5>
            <p class="text-muted" style="font-size:0.75rem;">Complete logs for all admin and seller confirmations.</p>
        </div>
    </a>
</div>

<!-- Pending Discount Approvals Section -->
<div id="discount_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>💰 Pending Discount Approvals</h4>
        <span class="badge <?= count($discountPending) > 0 ? 'badge-pending' : 'badge-approved' ?>">
            <?= count($discountPending) ?> pending
        </span>
    </div>

    <?php if(count($discountPending) > 0): ?>
        <?php foreach($discountPending as $dr): ?>
        <div class="discount-approval-card">
            <div class="meta-grid">
                <div>
                    <div class="meta-label">Product</div>
                    <div style="font-weight:500;"><?= htmlspecialchars($dr['product_name']) ?></div>
                </div>
                <div>
                    <div class="meta-label">Seller</div>
                    <div style="font-weight:500;"><?= htmlspecialchars($dr['seller_name']) ?></div>
                </div>
            </div>
            <div class="flex gap-2 mb-2" style="align-items:center;">
                <span class="price-strike">₵<?= number_format($dr['original_price'], 2) ?></span>
                <span style="margin:0 0.3rem;">→</span>
                <span class="price-new">₵<?= number_format($dr['discounted_price'], 2) ?></span>
                <span class="badge badge-pending" style="margin-left:auto;">−<?= $dr['discount_percent'] ?>%</span>
            </div>
            <div class="flex gap-1">
                <a href="?disc_action=approve_discount&disc_id=<?= $dr['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this discount? The product price will be updated for all buyers.')">✓ Approve</a>
                <a href="?disc_action=reject_discount&disc_id=<?= $dr['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this discount?')">✕ Reject</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted" style="text-align:center; padding:1.5rem;">✅ No pending discount requests. All clear!</p>
    <?php endif; ?>
</div>

<?php
// Handle dispute resolution
if (isset($_GET['dispute_action']) && isset($_GET['dispute_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $did = (int)$_GET['dispute_id'];
    $dact = $_GET['dispute_action'];
    $note = $_POST['admin_note'] ?? 'Resolved by admin';
    if ($dact === 'resolve_dispute') {
        $pdo->prepare("UPDATE disputes SET status='resolved', admin_note=? WHERE id=?")->execute([$note, $did]);
        auditLog($pdo, $_SESSION['user_id'], "Resolved dispute #$did", 'dispute', $did);
    }
}
?>

<!-- Active Disputes Section -->
<div id="disputes_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>🚨 Active Disputes & Conflicts</h4>
        <span class="badge badge-pending">Action Required</span>
    </div>

    <?php
    $openDisputes = $pdo->query("
        SELECT d.*, b.username as comp_name, t.username as target_name 
        FROM disputes d 
        JOIN users b ON d.complainant_id = b.id 
        JOIN users t ON d.target_id = t.id 
        WHERE d.status='open' ORDER BY d.created_at DESC
    ")->fetchAll();
    ?>

    <?php if(count($openDisputes) > 0): ?>
        <?php foreach($openDisputes as $d): ?>
        <div class="discount-approval-card" style="border-left: 4px solid var(--danger); margin-bottom:1rem;">
            <div class="flex-between mb-1">
                <strong>Dispute on Order #<?= $d['order_id'] ?></strong>
                <span class="badge badge-rejected"><?= date('M d, H:i', strtotime($d['created_at'])) ?></span>
            </div>
            <p style="font-size:0.85rem; margin-bottom:0.5rem;">
                <span style="color:var(--danger); font-weight:700;">Complainant:</span> <?= htmlspecialchars($d['comp_name']) ?> vs. <span style="font-weight:700;">Target:</span> <?= htmlspecialchars($d['target_name']) ?>
            </p>
            <div style="background:rgba(255,59,48,0.05); padding:0.75rem; border-radius:10px; font-size:0.85rem; border:1px solid rgba(255,59,48,0.1); margin-bottom:1rem;">
                "<?= htmlspecialchars($d['reason']) ?>"
            </div>
            <form method="POST" action="?dispute_action=resolve_dispute&dispute_id=<?= $d['id'] ?>" class="flex gap-1" onsubmit="return confirm('Resolve dispute? Verify with both parties first.')">
                <?= csrf_field() ?>
                <input type="text" name="admin_note" placeholder="Resolution note..." class="form-control" style="font-size:0.8rem; height:auto; padding:0.5rem;" required>
                <button class="btn btn-primary btn-sm">Resolve</button>
                <a href="messages.php?view=chat&u1=<?= $d['complainant_id'] ?>&u2=<?= $d['target_id'] ?>" class="btn btn-outline btn-sm">Monitor Chat</a>
            </form>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center" style="padding:1.5rem;">✅ No active disputes. The marketplace is harmonious!</p>
    <?php endif; ?>
</div>

<!-- PREMIUM BADGE REQUESTS SECTION -->
<div id="premium_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>⭐ Premium Badge Requests</h4>
    </div>

    <?php if(count($premiumPending) > 0): ?>
        <?php foreach($premiumPending as $req): ?>
        <div class="discount-approval-card" style="border-left: 4px solid var(--gold);">
            <div class="meta-grid">
                <div>
                    <div class="meta-label">Seller</div>
                    <div style="font-weight:500; font-size:1.1rem;"><?= htmlspecialchars($req['sender_name']) ?></div>
                </div>
                <div>
                    <div class="meta-label">Message</div>
                    <div style="font-size:0.9rem; color:var(--text-muted);"><?= htmlspecialchars($req['message']) ?></div>
                </div>
            </div>
            <div class="flex gap-1 mt-2">
                <?php if($req['seller_tier'] === 'premium'): ?>
                    <span class="badge badge-approved">✅ Accomplished</span>
                <?php else: ?>
                    <a href="users.php?action=upgrade_premium&id=<?= $req['sender_id'] ?>" class="btn btn-gold btn-sm">⭐ Upgrade to Premium</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted" style="text-align:center; padding:1.5rem;">✅ No pending premium requests.</p>
    <?php endif; ?>
</div>

<!-- PROFILE EDIT REQUESTS Section -->
<div id="profile_section" class="glass fade-in mt-3" style="padding:1.5rem;">
    <div class="flex-between mb-2">
        <h4>🛡️ Profile Edit Requests</h4>
        <span class="badge <?= count($profileByUser) > 0 ? 'badge-pending' : 'badge-approved' ?>">
            <?= count($profileByUser) ?> users pending
        </span>
    </div>

    <?php if(count($profileByUser) > 0): ?>
        <?php foreach($profileByUser as $uid => $data): ?>
        <div class="discount-approval-card" style="border-left:4px solid #a855f7; margin-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.8rem;">
                <?php if(!empty($data['profile_pic'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($data['profile_pic']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="">
                <?php else: ?>
                    <div style="width:36px;height:36px;border-radius:50%;background:rgba(168,85,247,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;color:#a855f7;"><?= strtoupper(substr($data['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <strong style="font-size:1rem;"><?= htmlspecialchars($data['username']) ?></strong>
                    <p class="text-muted" style="font-size:0.72rem;">User #<?= $uid ?> · <?= count($data['edits']) ?> change(s)</p>
                </div>
            </div>

            <div style="display:grid; gap:0.4rem; margin-bottom:0.8rem;">
                <?php foreach($data['edits'] as $edit): ?>
                <div style="display:grid; grid-template-columns:120px 1fr 20px 1fr; gap:0.5rem; align-items:center; padding:0.5rem 0.75rem; background:rgba(0,0,0,0.02); border-radius:8px; font-size:0.82rem;">
                    <span style="font-weight:600; text-transform:capitalize; color:var(--primary);"><?= htmlspecialchars(str_replace('_',' ',$edit['field_name'])) ?></span>
                    <span style="color:var(--text-muted); text-decoration:line-through; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($edit['old_value'] ?: '(empty)') ?></span>
                    <span style="text-align:center;">→</span>
                    <span style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($edit['new_value'] ?: '(empty)') ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-1">
                <a href="?profile_action=approve_all_user&uid=<?= $uid ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve ALL changes for <?= htmlspecialchars($data['username']) ?>?')">✓ Approve All</a>
                <?php foreach($data['edits'] as $edit): ?>
                    <a href="?profile_action=reject_profile&req_id=<?= $edit['id'] ?>" class="btn btn-danger btn-sm" style="font-size:0.7rem;">✕ Reject <?= ucfirst(str_replace('_',' ',$edit['field_name'])) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted" style="text-align:center; padding:1.5rem;">✅ No pending profile edit requests.</p>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
