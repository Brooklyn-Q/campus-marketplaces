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
        <span style="background:rgba(0,113,227,0.1); color:var(--primary); padding:0.5rem 1.2rem; border-radius:20px; font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:1.5rem; display:inline-block;">Ranking</span>
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
                    $rankEmoji = ['🥇','🥈','🥉'][$index] ?? null;
                    $rankColor = $isTop3 ? $colors[$index] : 'var(--text-muted)';
                ?>
                <div class="leaderboard-grid" style="cursor:pointer;" onmouseover="this.style.background='rgba(0,113,227,0.04)'" onmouseout="this.style.background='transparent'" onclick="window.location.href='chat.php?user=<?= $l['id'] ?>'">
                    <!-- Rank Column -->
                    <div style="text-align:center;">
                        <?php if($rankEmoji): ?>
                            <span style="font-size:1.8rem; filter:drop-shadow(0 4px 8px rgba(0,0,0,0.1));"><?= $rankEmoji ?></span>
                        <?php else: ?>
                            <span style="font-size:1.1rem; font-weight:800; color:var(--text-muted); opacity:0.6;">#<?= ($index+1) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info Column -->
                    <div style="display:flex; align-items:center; gap:1.25rem;">
                        <div style="position:relative;">
                            <?php if($l['profile_pic']): ?>
                                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($l['profile_pic'])) ?>" alt="<?= htmlspecialchars($l['username']) ?>" class="profile-pic-previewable" style="width:56px; height:56px; border-radius:18px; object-fit:cover; border:2px solid <?= $isTop3 ? $rankColor : 'rgba(128,128,128,0.1)' ?>; box-shadow:0 8px 16px rgba(0,0,0,0.05); cursor:pointer;">
                            <?php else: ?>
                                <div style="width:56px; height:56px; border-radius:18px; background:linear-gradient(135deg, rgba(0,113,227,0.1), rgba(0,113,227,0.05)); color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.4rem; border:2px solid <?= $isTop3 ? $rankColor : 'rgba(128,128,128,0.1)' ?>;">
                                    <?= strtoupper(substr($l['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <?php if($l['verified']): ?>
                                <div style="position:absolute; bottom:-4px; right:-4px; background:var(--success); color:#fff; width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">✓</div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="overflow:hidden;">
                            <h4 style="font-size:1rem; font-weight:700; color:var(--text-main); margin:0; display:flex; align-items:center; gap:6px;">
                                <?= htmlspecialchars($l['username']) ?>
                                <?php if($l['seller_tier']==='premium'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#facc15"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <?php endif; ?>
                            </h4>
                            <p style="color:var(--text-muted); font-size:0.75rem; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500;">
                                <?= htmlspecialchars($l['department']) ?: 'Verified Seller' ?>
                            </p>
                        </div>
                    </div>

                    <!-- Statistics Column -->
                    <div style="text-align:center;">
                        <span style="font-size:1.1rem; font-weight:800; color:var(--text-main); letter-spacing:-0.4px;"><?= ($l['sales_today'] > 0) ? $l['sales_today'] : $l['lifetime_sales'] ?></span>
                        <div style="font-size:0.55rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.4px; margin-top:-1px;">
                            <?= ($l['sales_today'] > 0) ? 'Sold Today' : 'Sold Total' ?>
                        </div>
                    </div>

                    <!-- Badge/Status Column -->
                    <div style="text-align:right;" class="hide-mobile">
                        <?php if($l['sales_today'] > 0): ?>
                            <div style="display:inline-flex; align-items:center; gap:4px; background:rgba(0, 113, 227, 0.1); color:var(--primary); padding:0.4rem 0.8rem; border-radius:10px; font-size:0.75rem; font-weight:800; border:1px solid rgba(0,113,227,0.2);">
                                <span>🚀</span> Trending
                            </div>
                        <?php elseif($l['lifetime_sales'] > 10): ?>
                            <div style="display:inline-flex; align-items:center; gap:4px; background:rgba(250, 204, 21, 0.1); color:#ca8a04; padding:0.4rem 0.8rem; border-radius:10px; font-size:0.75rem; font-weight:800; border:1px solid rgba(250,204,21,0.2);">
                                <span>💎</span> Power Seller
                            </div>
                        <?php else: ?>
                            <div style="display:inline-flex; align-items:center; gap:4px; background:rgba(128, 128, 128, 0.08); color:var(--text-muted); padding:0.4rem 0.8rem; border-radius:10px; font-size:0.75rem; font-weight:700; border:1px solid rgba(128,128,128,0.15);">
                                Growing
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding:4rem; text-align:center; color:var(--text-muted);">
                <span style="font-size:3rem; display:block; margin-bottom:1rem;">🏆</span>
                <p style="font-size:1.1rem; font-weight:600;">The leaderboard is warming up...</p>
                <p style="font-size:0.9rem; opacity:0.7;">Be the first to list and sell to claim your spot!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
