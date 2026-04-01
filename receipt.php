<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

if (!isset($_GET['ref'])) redirect('dashboard.php');
$ref = $_GET['ref'];

$stmt = $pdo->prepare("SELECT t.*, u.username as buyer_name, u.email as buyer_email FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.reference = ?");
$stmt->execute([$ref]);
$tx = $stmt->fetch();

if (!$tx || ($tx['user_id'] != $_SESSION['user_id'] && !isAdmin())) {
    redirect('dashboard.php');
}

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in" style="max-width:500px; padding:3rem;" id="receiptCanvas">
    <div class="text-center mb-3">
        <h1 style="color:var(--primary); font-size:2rem; margin:0;">Receipt</h1>
        <p class="text-muted" style="margin-top:0.5rem;">Campus Marketplace</p>
    </div>

    <div style="border-top:1px dashed var(--border); border-bottom:1px dashed var(--border); padding:1.5rem 0; margin-bottom:1.5rem;">
        <div class="flex-between mb-1"><span class="text-muted">Transaction Ref:</span> <strong><?= htmlspecialchars($tx['reference']) ?></strong></div>
        <div class="flex-between mb-1"><span class="text-muted">Date:</span> <strong><?= date('M d, Y H:i A', strtotime($tx['created_at'])) ?></strong></div>
        <div class="flex-between mb-1"><span class="text-muted">Status:</span> <strong style="color:var(--success);"><?= strtoupper($tx['status']) ?></strong></div>
        <div class="flex-between"><span class="text-muted">Payment Type:</span> <strong><?= ucfirst($tx['type']) ?></strong></div>
    </div>

    <div class="mb-3">
        <p class="text-muted mb-1" style="font-size:0.85rem;">Description</p>
        <p style="font-size:1.1rem; line-height:1.4;"><?= htmlspecialchars($tx['description']) ?></p>
    </div>

    <div style="background:rgba(99,102,241,0.1); padding:1.5rem; border-radius:12px; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:1.2rem; font-weight:600;">Total Amount</span>
        <span style="font-size:1.8rem; font-weight:800; color:var(--primary);">₵<?= number_format($tx['amount'], 2) ?></span>
    </div>

    <div class="text-center mt-4">
        <p class="text-muted" style="font-size:0.8rem;">Thank you for trusting Campus Marketplace.</p>
    </div>
</div>

<div class="text-center mb-4">
    <button onclick="window.print()" class="btn btn-primary" style="margin-right:1rem;">🖨️ Print Receipt</button>
    <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #receiptCanvas, #receiptCanvas * { visibility: visible; }
    #receiptCanvas { position: absolute; left: 0; top: 0; width:100%; box-shadow:none; border:none; background:white; color:black; }
    .text-muted { color: #666 !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
