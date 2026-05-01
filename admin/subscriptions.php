<?php
$page_title = 'Subscription History';
require_once 'header.php';

// Fetch subscription history with user details
try {
    $stmt = $pdo->query("
        SELECT s.*, u.username, u.email 
        FROM tier_subscriptions s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.purchased_at DESC
    ");
    $subscriptions = $stmt ? $stmt->fetchAll() : [];

    // Fetch summary stats
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_count, 
            SUM(amount) as total_revenue,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_count
        FROM tier_subscriptions
    ");
    $stats = $statsStmt ? $statsStmt->fetch() : ['total_revenue' => 0, 'active_count' => 0];
} catch (PDOException $e) {
    // If the table doesn't exist yet, show empty stats instead of crashing
    $subscriptions = [];
    $stats = ['total_revenue' => 0, 'active_count' => 0];
    error_log("Subscriptions page error: " . $e->getMessage());
}
?>

<div class="fade-in">
    <div class="flex-between mb-3">
        <div>
            <h2 style="margin:0;">💳 Subscription Ledger</h2>
            <p class="text-muted">Permanent record of all tier upgrades and payments.</p>
        </div>
        <div style="display:flex; gap:1rem;">
            <div class="glass" style="padding:0.75rem 1.25rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Total Revenue</div>
                <div style="font-size:1.1rem; font-weight:800; color:var(--gold);">GHS <?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
            </div>
            <div class="glass" style="padding:0.75rem 1.25rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Active Plans</div>
                <div style="font-size:1.1rem; font-weight:800; color:#22c55e;"><?= (int)$stats['active_count'] ?></div>
            </div>
        </div>
    </div>

    <div class="glass" style="padding:0; overflow:hidden;">
        <table class="responsive-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:rgba(255,255,255,0.03); border-bottom:1px solid var(--border);">
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">USER</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">TIER</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">PRICE</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">PURCHASED</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">EXPIRATION</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">TRANSACTION ID</th>
                    <th style="padding:1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)): ?>
                    <tr>
                        <td colspan="7" style="padding:3rem; text-align:center; color:var(--text-muted);">
                            No subscriptions found in the ledger.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $s): ?>
                        <?php 
                        $isLifetime = empty($s['expires_at']);
                        $isExpired = !$isLifetime && strtotime($s['expires_at']) < time();
                        $tierColor = ($s['tier_name'] === 'premium') ? 'var(--gold)' : (($s['tier_name'] === 'pro') ? '#8e8e93' : '#fff');
                        $transactionId = (string) ($s['transaction_id'] ?? '');
                        $isPaystackTransaction = $transactionId !== '' && !str_starts_with($transactionId, 'ADMIN_UPGRADE_');
                        ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td data-label="User" style="padding:1rem;">
                                <div style="font-weight:700;"><?= htmlspecialchars($s['username']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <td data-label="Tier" style="padding:1rem;">
                                <span class="badge" style="background:rgba(255,255,255,0.05); color:<?= $tierColor ?>; border:1px solid <?= $tierColor ?>44;">
                                    <?= strtoupper($s['tier_name']) ?>
                                </span>
                            </td>
                            <td data-label="Price" style="padding:1rem; font-weight:600;">
                                GHS <?= number_format($s['amount'], 2) ?>
                            </td>
                            <td data-label="Purchased" style="padding:1rem; font-size:0.85rem;">
                                <?= date('M d, Y', strtotime($s['purchased_at'])) ?>
                                <div style="font-size:0.7rem; color:var(--text-muted);"><?= date('H:i:s', strtotime($s['purchased_at'])) ?></div>
                            </td>
                            <td data-label="Expiration" style="padding:1rem; font-size:0.85rem;">
                                <?php if ($isLifetime): ?>
                                    <span style="color:var(--gold); font-weight:700;">LIFETIME</span>
                                <?php else: ?>
                                    <?= date('M d, Y', strtotime($s['expires_at'])) ?>
                                    <div style="font-size:0.7rem; color:var(--text-muted);"><?= date('H:i:s', strtotime($s['expires_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Transaction ID" style="padding:1rem; font-family:monospace; font-size:0.75rem;">
                                <?php if ($isPaystackTransaction): ?>
                                    <a href="https://dashboard.paystack.com/#/transactions/<?= htmlspecialchars($transactionId) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary); text-decoration:none;">
                                        <?= htmlspecialchars($transactionId) ?> ↗
                                    </a>
                                <?php else: ?>
                                    <span title="Internal admin-generated transaction"><?= htmlspecialchars($transactionId ?: '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status" style="padding:1rem;">
                                <?php if ($isExpired): ?>
                                    <span class="badge badge-danger" style="font-size:0.65rem;">EXPIRED</span>
                                <?php else: ?>
                                    <span class="badge badge-approved" style="font-size:0.65rem;">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
