<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch top sellers with "Smart Ranking"
// Priority 1: Items sold in the last 24 hours
// Priority 2: Lifetime Sales Count
// Priority 3: Active approved listings
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$salesTodaySql = $driver === 'pgsql'
    ? "CURRENT_TIMESTAMP - INTERVAL '1 day'"
    : "NOW() - INTERVAL 1 DAY";

$stmt = $pdo->query("
    SELECT u.id, u.username, u.profile_pic, u.department, u.seller_tier, u.verified,
           (SELECT COUNT(*) FROM products WHERE user_id = u.id AND status='approved') as active_listings,
           (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type='sale' AND created_at >= $salesTodaySql) as sales_today,
           (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND type='sale') as lifetime_sales,
           COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type='sale'), 0) as total_earnings
    FROM users u
    WHERE u.role IN ('seller', 'admin')
    GROUP BY u.id
    ORDER BY sales_today DESC, lifetime_sales DESC, active_listings DESC
    LIMIT 10
");
$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$colors = ['#FFD700', '#C0C0C0', '#CD7F32']; // Gold, Silver, Bronze
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    
    .leaderboard-container {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    .leaderboard-grid {
        display: grid;
        grid-template-columns: 80px 1fr 100px 180px;
        gap: 1.5rem;
        padding: 1.25rem 2rem;
        align-items: center;
        border-bottom: 1px solid rgba(128,128,128,0.06);
        transition: all 0.2s ease;
    }
    
    .leaderboard-header {
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1.5px;
        border-bottom: 2px solid var(--border);
        background: rgba(0,0,0,0.02);
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    
    /* RANK COLUMN ALIGNMENT */
    .leaderboard-grid > div:nth-child(1) {
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    /* SELLER DETAILS COLUMN ALIGNMENT */
    .leaderboard-grid > div:nth-child(2) {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .leaderboard-grid > div:nth-child(2) .profile-pic {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border);
    }
    
    .leaderboard-grid > div:nth-child(2) .seller-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .leaderboard-grid > div:nth-child(2) .seller-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-main);
        margin: 0;
    }
    
    .leaderboard-grid > div:nth-child(2) .seller-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin: 0;
    }
    
    /* DEALS COLUMN ALIGNMENT */
    .leaderboard-grid > div:nth-child(3) {
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 0.25rem;
    }
    
    .leaderboard-grid > div:nth-child(3) .deals-count {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-main);
    }
    
    .leaderboard-grid > div:nth-child(3) .deals-label {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* PERFORMANCE COLUMN ALIGNMENT */
    .leaderboard-grid > div:nth-child(4) {
        text-align: right;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-end;
        gap: 0.25rem;
    }
    
    .leaderboard-grid > div:nth-child(4) .performance-earnings {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success);
    }
    
    .leaderboard-grid > div:nth-child(4) .performance-listings {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    
    /* HEADER ALIGNMENT */
    .leaderboard-header > div:nth-child(1) {
        text-align: center;
    }
    
    .leaderboard-header > div:nth-child(2) {
        text-align: left;
    }
    
    .leaderboard-header > div:nth-child(3) {
        text-align: center;
    }
    
    .leaderboard-header > div:nth-child(4) {
        text-align: right;
    }
    
    /* RANK BADGE STYLING */
    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-weight: 800;
        font-size: 0.9rem;
        color: white;
    }
    
    .rank-badge.gold {
        background: linear-gradient(135deg, #FFD700, #FFA500);
    }
    
    .rank-badge.silver {
        background: linear-gradient(135deg, #C0C0C0, #808080);
    }
    
    .rank-badge.bronze {
        background: linear-gradient(135deg, #CD7F32, #8B4513);
    }
    
    .rank-badge.regular {
        background: var(--border);
        color: var(--text-muted);
    }
    
    /* VERIFIED BADGE */
    .verified-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* TIER BADGE */
    .tier-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        background: rgba(124, 58, 237, 0.1);
        color: var(--primary);
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* HOVER EFFECTS */
    .leaderboard-grid:hover {
        background: rgba(124, 58, 237, 0.04);
        transform: translateY(-1px);
    }
    
    .leaderboard-grid:hover .rank-badge {
        transform: scale(1.05);
    }
    
    /* SMOOTH TRANSITIONS */
    .leaderboard-grid,
    .rank-badge,
    .verified-badge,
    .tier-badge {
        transition: all 0.2s ease;
    }
    @media (max-width: 768px) {
        .leaderboard-grid {
            grid-template-columns: 50px 1fr 70px;
            gap: 0.75rem;
            padding: 1rem 0.5rem;
        }
        .hide-mobile { display: none !important; }
        .leaderboard-header { font-size: 0.65rem; }
    }
</style>

<div class="container fade-in leaderboard-container" style="max-width:960px; padding:3rem 5%;">
    <div style="text-align:center; margin-bottom:4rem;">
        <span style="background:rgba(124,58,237,0.1); color:var(--primary); padding:0.5rem 1.2rem; border-radius:20px; font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:1.5rem; display:inline-block;">Ranking</span>
        <h1 style="font-size:3.5rem; font-weight:900; letter-spacing:-0.05em; color:var(--text-main); line-height:1.1; margin-bottom:1rem;">Seller Leaderboard</h1>
        <p style="color:var(--text-muted); font-size:1.2rem; max-width:600px; margin:0 auto; font-weight:500;">Recognizing our most dedicated campus sellers. Activity and sales drive the rankings.</p>
    </div>

    <div class="glass" style="border-radius:28px; overflow:hidden; border:1px solid rgba(255,255,255,0.1); box-shadow:0 20px 40px rgba(0,0,0,0.08);">
        <!-- Table Header -->
        <div class="leaderboard-grid leaderboard-header">
            <div style="text-align:center;">Rank</div>
            <div>Seller Details</div>
            <div style="text-align:center;">Deals</div>
            <div style="text-align:right;" class="hide-mobile">Performance</div>
        </div>

        <?php if(count($leaders) > 0): ?>
            <?php foreach($leaders as $index => $l): ?>
                <?php 
                    $isTop3 = $index < 3; 
                    $rankEmoji = null;
                    $rankColor = $isTop3 ? $colors[$index] : 'var(--text-muted)';
                ?>
                <div class="leaderboard-grid" style="cursor:pointer;" onmouseover="this.style.background='rgba(124,58,237,0.04)'" onmouseout="this.style.background='transparent'" onclick="window.location.href='chat.php?user=<?= $l['id'] ?>'">
                    <!-- Rank Column -->
                    <div>
                        <?php if($rankEmoji): ?>
                            <span style="font-size:1.8rem; filter:drop-shadow(0 4px 8px rgba(0,0,0,0.1));"><?= $rankEmoji ?></span>
                        <?php else: ?>
                            <div class="rank-badge regular">#<?= ($index+1) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info Column -->
                    <div>
                        <div style="position:relative;">
                            <?php if($l['profile_pic']): ?>
                                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($l['profile_pic'])) ?>" alt="<?= htmlspecialchars($l['username']) ?>" class="profile-pic-previewable profile-pic">
                            <?php else: ?>
                                <div class="profile-pic" style="background:linear-gradient(135deg, rgba(124,58,237,0.1), rgba(124,58,237,0.05)); color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.4rem;">
                                    <?= strtoupper(substr($l['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <?php if($l['verified']): ?>
                                <div style="position:absolute; bottom:-4px; right:-4px; background:var(--success); color:#fff; width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">✓</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="seller-info">
                            <h4 class="seller-name">
                                <?= htmlspecialchars($l['username']) ?>
                                <?php if($l['seller_tier']==='premium'): ?>
                                    <span class="tier-badge">Premium</span>
                                <?php endif; ?>
                            </h4>
                            <p class="seller-meta">
                                <?= htmlspecialchars((string)($l['department'] ?? '')) ?: 'Verified Seller' ?>
                                <?php if($l['verified']): ?>
                                    <span class="verified-badge">✓ Verified</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Statistics Column -->
                    <div>
                        <div class="deals-count"><?= ($l['sales_today'] > 0) ? $l['sales_today'] : $l['lifetime_sales'] ?></div>
                        <div class="deals-label"><?= ($l['sales_today'] > 0) ? 'Sold Today' : 'Sold Total' ?></div>
                    </div>

                    <!-- Performance Column -->
                    <div class="hide-mobile">
                        <div class="performance-earnings">GHS <?= number_format($l['total_earnings'], 0) ?></div>
                        <div class="performance-listings"><?= $l['active_listings'] ?> listings</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding:4rem; text-align:center; color:var(--text-muted);">
                                <p style="font-size:1.1rem; font-weight:600;">The leaderboard is warming up...</p>
                <p style="font-size:0.9rem; opacity:0.7;">Be the first to list and sell to claim your spot!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
