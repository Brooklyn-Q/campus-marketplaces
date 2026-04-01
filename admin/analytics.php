<?php
$page_title = 'Analytics';
require_once 'header.php';

// ── Core Metrics ──
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed'")->fetchColumn();
$totalBoostRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='boost' AND status='completed'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$activeSellers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM products WHERE status='approved'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type IN ('sale','purchase')")->fetchColumn();
$totalViews = $pdo->query("SELECT COALESCE(SUM(views),0) FROM products")->fetchColumn();
$newUsersToday = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// ── Daily Revenue (last 14 days) ──
$dailyRevenue = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type IN ('sale','boost','premium') AND status='completed' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $dailyRevenue[] = ['label' => $label, 'value' => (float)$stmt->fetchColumn()];
}

// ── Daily New Users (last 14 days) ──
$dailyUsers = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $dailyUsers[] = ['label' => $label, 'value' => (int)$stmt->fetchColumn()];
}

// ── Top 10 Products by Views ──
$topViewed = $pdo->query("SELECT p.title, p.views, p.price, u.username as seller FROM products p JOIN users u ON p.user_id = u.id WHERE p.status='approved' ORDER BY p.views DESC LIMIT 10")->fetchAll();

// ── Top 10 Products by Sales ──
$topSelling = $pdo->query("SELECT p.title, p.clicks as sales, p.price, u.username as seller FROM products p JOIN users u ON p.user_id = u.id ORDER BY p.clicks DESC LIMIT 10")->fetchAll();

// ── Top 5 Seller Performers ──
$topSellers = $pdo->query("SELECT u.username, u.seller_tier, COUNT(t.id) as sale_count, COALESCE(SUM(t.amount),0) as revenue FROM users u LEFT JOIN transactions t ON u.id = t.user_id AND t.type = 'sale' WHERE u.role = 'seller' GROUP BY u.id ORDER BY revenue DESC LIMIT 5")->fetchAll();

// ── Category Distribution ──
$categoryDist = $pdo->query("SELECT category, COUNT(*) as cnt FROM products WHERE status='approved' GROUP BY category ORDER BY cnt DESC")->fetchAll();
?>

<h2 class="mb-3" style="display:flex; align-items:center; gap:0.5rem;">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Analytics Dashboard
</h2>

<!-- KPI Cards -->
<div class="stat-grid mb-3 fade-in" style="grid-template-columns:repeat(auto-fill, minmax(160px, 1fr));">
    <div class="glass stat-card"><div class="stat-val" style="color:var(--success);">₵<?= number_format($totalRevenue, 2) ?></div><div class="stat-label">Total Revenue</div></div>
    <div class="glass stat-card"><div class="stat-val" style="color:var(--gold);">₵<?= number_format($totalBoostRevenue, 2) ?></div><div class="stat-label">Boost Revenue</div></div>
    <div class="glass stat-card"><div class="stat-val" style="color:var(--primary);"><?= $totalUsers ?></div><div class="stat-label">Total Users</div></div>
    <div class="glass stat-card"><div class="stat-val"><?= $totalProducts ?></div><div class="stat-label">Total Products</div></div>
    <div class="glass stat-card"><div class="stat-val" style="color:#ff9500;"><?= $activeSellers ?></div><div class="stat-label">Active Sellers</div></div>
    <div class="glass stat-card"><div class="stat-val" style="color:#af52de;"><?= $totalOrders ?></div><div class="stat-label">Total Orders</div></div>
    <div class="glass stat-card"><div class="stat-val"><?= number_format($totalViews) ?></div><div class="stat-label">Total Views</div></div>
    <div class="glass stat-card"><div class="stat-val" style="color:var(--success);">+<?= $newUsersToday ?></div><div class="stat-label">New Today</div></div>
</div>

<!-- Charts Row -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <!-- Revenue Chart -->
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">📈 Revenue (14 Days)</h4>
        <canvas id="revenueChart" height="160"></canvas>
    </div>
    <!-- User Growth Chart -->
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">👥 User Sign-ups (14 Days)</h4>
        <canvas id="userChart" height="160"></canvas>
    </div>
</div>

<!-- Category Distribution + Top Sellers -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">📊 Category Distribution</h4>
        <canvas id="categoryChart" height="200"></canvas>
    </div>
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">🏆 Top Sellers</h4>
        <table>
            <thead><tr><th>#</th><th>Seller</th><th>Tier</th><th>Sales</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php foreach($topSellers as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['username']) ?></td>
                    <td><span class="badge <?= $s['seller_tier']==='premium' ? 'badge-gold' : 'badge-blue' ?>" style="font-size:0.6rem;"><?= ucfirst($s['seller_tier']) ?></span></td>
                    <td><?= $s['sale_count'] ?></td>
                    <td style="font-weight:700; color:var(--success);">₵<?= number_format($s['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Viewed + Top Selling -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">👁 Most Viewed Products</h4>
        <table>
            <thead><tr><th>#</th><th>Product</th><th>Seller</th><th>Views</th><th>Price</th></tr></thead>
            <tbody>
                <?php foreach($topViewed as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td style="font-weight:500; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['title']) ?></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($p['seller']) ?></td>
                    <td style="font-weight:700;"><?= number_format($p['views']) ?></td>
                    <td>₵<?= number_format($p['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="glass fade-in" style="padding:1.5rem;">
        <h4 class="mb-2" style="font-size:0.95rem;">🔥 Top Selling Products</h4>
        <table>
            <thead><tr><th>#</th><th>Product</th><th>Seller</th><th>Sales</th><th>Price</th></tr></thead>
            <tbody>
                <?php foreach($topSelling as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td style="font-weight:500; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['title']) ?></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($p['seller']) ?></td>
                    <td style="font-weight:700;"><?= $p['sales'] ?></td>
                    <td>₵<?= number_format($p['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartColors = {
        revenue: { bg: 'rgba(0,113,227,0.15)', border: '#0071e3' },
        users: { bg: 'rgba(52,199,89,0.15)', border: '#34c759' },
    };
    const gridColor = 'rgba(0,0,0,0.04)';
    const tickColor = '#86868b';

    // Revenue Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($dailyRevenue, 'label')) ?>,
            datasets: [{
                label: 'Revenue (₵)',
                data: <?= json_encode(array_column($dailyRevenue, 'value')) ?>,
                backgroundColor: chartColors.revenue.bg,
                borderColor: chartColors.revenue.border,
                borderWidth: 2, borderRadius: 6, barPercentage: 0.7,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: tickColor, callback: v => '₵' + v }, grid: { color: gridColor } }, x: { ticks: { color: tickColor, maxRotation: 45 }, grid: { display: false } } } }
    });

    // User Chart
    new Chart(document.getElementById('userChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($dailyUsers, 'label')) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode(array_column($dailyUsers, 'value')) ?>,
                backgroundColor: chartColors.users.bg,
                borderColor: chartColors.users.border,
                borderWidth: 2, fill: true, tension: 0.4,
                pointBackgroundColor: chartColors.users.border,
                pointRadius: 3,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: tickColor, stepSize: 1 }, grid: { color: gridColor } }, x: { ticks: { color: tickColor, maxRotation: 45 }, grid: { display: false } } } }
    });

    // Category Doughnut
    const catData = <?= json_encode($categoryDist) ?>;
    const catColors = ['#0071e3','#34c759','#ff9500','#af52de','#ff3b30','#5ac8fa','#ffcc00','#8e8e93'];
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catData.map(c => c.category),
            datasets: [{
                data: catData.map(c => c.cnt),
                backgroundColor: catColors.slice(0, catData.length),
                borderWidth: 0,
            }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, color: tickColor, padding: 12 } } } }
    });
});
</script>

<style>
    @media(max-width:768px) {
        div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns:1fr !important; }
    }
</style>

<?php require_once 'footer.php'; ?>
