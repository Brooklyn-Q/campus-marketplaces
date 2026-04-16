<?php 
require_once __DIR__ . '/db.php'; 

if (isLoggedIn()) {
    $current_file = basename($_SERVER['PHP_SELF']);
    // Skip enforcement for certain pages to prevent redirect loops
    $exempt_pages = ['terms.php', 'logout.php', 'login.php', 'register.php'];
    
    if (!in_array($current_file, $exempt_pages)) {
        $user_meta = getUser($pdo, $_SESSION['user_id']);
        if ($user_meta && !$user_meta['terms_accepted']) {
            redirect('terms.php');
        }
    }

    if (($_SESSION['role'] ?? '') !== 'admin') {
        try {
            $stmt_unrev = $pdo->prepare("SELECT o.product_id FROM orders o LEFT JOIN reviews r ON o.product_id=r.product_id AND o.buyer_id=r.user_id WHERE o.buyer_id=? AND o.status='completed' AND r.id IS NULL LIMIT 1");
            $stmt_unrev->execute([$_SESSION['user_id']]);
            if ($needs_review = $stmt_unrev->fetchColumn()) {
                if ($current_file !== 'product.php' && $current_file !== 'logout.php' && $current_file !== 'terms.php' && empty($_GET['action'])) {
                    header("Location: product.php?id=$needs_review&review_required=1#review");
                    exit;
                }
            }
        } catch(PDOException $e) {}
    }
}

if (file_exists(__DIR__ . '/../.maintenance') && !isAdmin()) {
    if (strpos($_SERVER['REQUEST_URI'], '/login.php') === false && strpos($_SERVER['REQUEST_URI'], '/api/') === false) {
        die("<!DOCTYPE html><html><head><title>Under Maintenance</title><style>body{background:#0a0f1e;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;}</style></head><body><div><h1>🚧 We're currently down for maintenance.</h1><p>Our engineers are upgrading the campus marketplace. We'll be back shortly!</p><br><a href='/marketplace/login.php' style='color:#6366f1;text-decoration:none;'>Admin Login &rarr;</a></div></body></html>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Campus Marketplace — Buy and sell safely within your university community.">
    <title>Campus Marketplace</title>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
        }
        window.MARKETPLACE_BASE_URL = '<?= $baseUrl ?>';
    </script>
    <!-- UPDATED: Fonts loaded via CSS @import -->
    <link rel="stylesheet" href="<?= getAssetUrl('assets/css/style.css?v=1.1') ?>">
    
    <!-- LOAD REACT ASSETS EVERYWHERE -->
    <link rel="stylesheet" href="<?= getAssetUrl('assets/dist/app.css?v=1.1') ?>">
    <script type="module" src="<?= getAssetUrl('assets/dist/app.js?v=1.1') ?>"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
</head>
<body class="<?= isLoggedIn() ? 'is-logged-in' : '' ?>">
    <nav style="position:sticky; top:0; z-index:999999; backdrop-filter:saturate(180%) blur(24px); -webkit-backdrop-filter:saturate(180%) blur(24px); background:rgba(255,255,255,0.75); border-bottom:1px solid rgba(0,0,0,0.07); transition: background 0.3s, border-color 0.3s; padding: 0 5%;">
        <div style="display:flex; align-items:center; justify-content:space-between; height:58px; max-width:1400px; margin:0 auto; width:100%;">
            <!-- Brand -->
            <a href="<?= $baseUrl ?>" style="font-size:1.15rem; font-weight:800; color:var(--text-main); text-decoration:none; letter-spacing:-0.04em; display:flex; align-items:center; gap:6px; flex-shrink:0; transition:opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Campus Marketplace
            </a>

            <!-- Center Nav Links -->
            <div class="nav-links" id="mobileNavLinks" style="display:flex; align-items:center; justify-content:center; gap:1.25rem; flex:1; min-width:0;">
                <a href="<?= $baseUrl ?>" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Explore</a>

                <!-- Categories Dropdown -->
                <div class="cat-dropdown" style="position:relative; display:inline-block; flex-shrink:0;">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('catMenu').classList.toggle('cat-open');" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; cursor:pointer; display:flex; align-items:center; gap:4px; white-space:nowrap;">
                        Categories
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </a>
                    <div id="catMenu" class="cat-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 8px); left:50%; transform:translateX(-50%); width:240px; background:var(--card-bg); backdrop-filter:saturate(180%) blur(24px); -webkit-backdrop-filter:saturate(180%) blur(24px); border:1px solid var(--border); border-radius:16px; box-shadow:0 12px 48px rgba(0,0,0,0.12); overflow:hidden; z-index:999;">
                        <a href="<?= $baseUrl ?>index.php?category=Computer+%26+Accessories" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            Computer &amp; Accessories
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Phone+%26+Accessories" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            Phone &amp; Accessories
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Electrical+Appliances" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            Electrical Appliances
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Fashion" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.13z"/></svg>
                            Fashion
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Food+%26+Groceries" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                            Food &amp; Groceries
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Education+%26+Books" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            Education &amp; Books
                        </a>
                        <a href="<?= $baseUrl ?>index.php?category=Hostels+for+Rent" class="cat-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Hostels for Rent
                        </a>
                        <!-- Removed Others category -->
                    </div>
                </div>

                <style>
                    .cat-dropdown-menu.cat-open { display:block !important; animation: catFadeIn 0.18s ease; }
                    @keyframes catFadeIn { from { opacity:0; transform:translateX(-50%) translateY(-8px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
                    .cat-item { display:flex; align-items:center; gap:8px; padding:10px 16px; color:var(--text-main); text-decoration:none; font-size:0.84rem; border-bottom:1px solid rgba(0,0,0,0.05); transition:all 0.2s; }
                    .cat-item:hover { background:rgba(0,113,227,0.06); color:#0071e3; }
                    .cat-item:last-child { border-bottom:none; }
                    :root.dark-mode .cat-item { border-bottom-color:rgba(255,255,255,0.06); }
                    :root.dark-mode .cat-item:hover { background:rgba(255,255,255,0.08); }
                    @media (max-width: 768px) {
                        .cat-dropdown { width: 100%; display:block; }
                        #catMenu { position: relative !important; top: 0 !important; left: 0 !important; transform: none !important; width: 100% !important; box-shadow: none !important; border: none !important; background: transparent !important; margin-top: 8px; }
                        :root.dark-mode .cat-dropdown-menu { background: transparent !important; }
                    }
                </style>
                <script>document.addEventListener('click',function(e){if(!e.target.closest('.cat-dropdown')){var m=document.getElementById('catMenu');if(m)m.classList.remove('cat-open');}});</script>

                <?php if(isLoggedIn()): ?>
                    <?php $unread = getUnreadCount($pdo, $_SESSION['user_id']); ?>
                    <a href="<?= $baseUrl ?>leaderboard.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">🏆 Rank</a>
                    <a href="<?= $baseUrl ?>dashboard.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Dashboard</a>
                    <a href="<?= $baseUrl ?>chat.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; position:relative; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">
                        Messages
                        <span class="notif-badge msg-unread-badge" style="<?= $unread > 0 ? 'display:flex;' : 'display:none;' ?>"><?= $unread ?></span>
                    </a>
                    <?php if(isSeller() || isAdmin()): ?>
                        <a href="<?= $baseUrl ?>add_product.php" class="react-liquid-btn" data-label="+ Sell" style="flex-shrink:0;"></a>
                    <?php endif; ?>
                    <?php if(isAdmin()): ?>
                        <a href="<?= $baseUrl ?>admin/" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Admin</a>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>logout.php" style="color:var(--text-muted); font-weight:600; font-size:0.95rem; padding:0.55rem 0.9rem; border-radius:10px; transition:all 0.2s; text-decoration:none; white-space:nowrap; flex-shrink:0;" onmouseover="this.style.color='#ff3b30'; this.style.background='rgba(255,59,48,0.06)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Logout</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>login.php" style="color:var(--text-muted); font-weight:500; font-size:0.85rem; padding:0.4rem 0.75rem; border-radius:8px; transition:all 0.2s; text-decoration:none;" onmouseover="this.style.color='var(--text-main)'; this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">Login</a>
                    <a href="<?= $baseUrl ?>register.php" style="background:#0071e3; color:#fff; font-weight:600; font-size:0.85rem; padding:0.45rem 1.1rem; border-radius:980px; text-decoration:none; transition:all 0.2s;" onmouseover="this.style.background='#0080f8'" onmouseout="this.style.background='#0071e3'">Sign Up</a>
                <?php endif; ?>
            </div>

            <!-- Right-side icons -->
            <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                <div id="react-theme-toggle"></div>
                <a href="javascript:void(0)" onclick="if(typeof openSideCart === 'function') openSideCart(); return false;" style="position:relative; color:var(--text-main); text-decoration:none; padding:6px; border-radius:8px; transition:all 0.2s; display:flex; align-items:center; justify-content:center;" title="Cart" onmouseover="this.style.background='rgba(0,0,0,0.06)'" onmouseout="this.style.background='transparent'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="cart-count-badge" style="display:none; position:absolute; top:-5px; right:-6px; background:#ff3b30; color:#fff; font-size:0.6rem; font-weight:700; width:17px; height:17px; border-radius:50%; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(255,59,48,0.4);">0</span>
                </a>
                <button class="mobile-toggle" id="mobileNavToggle" onclick="toggleMobileNav()" style="color:var(--text-main); cursor:pointer; background:none; border:none; padding:6px; border-radius:8px;" aria-label="Toggle menu">
                    <svg id="menuIconOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    <svg id="menuIconClose" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </nav>
    <script>
    function toggleMobileNav() {
        var navLinks = document.getElementById('mobileNavLinks');
        var navBar = document.querySelector('nav');
        var openIcon = document.getElementById('menuIconOpen');
        var closeIcon = document.getElementById('menuIconClose');
        var isOpen = navLinks.classList.contains('open');
        if (isOpen) {
            navLinks.classList.remove('open');
            openIcon.style.display = '';
            closeIcon.style.display = 'none';
            document.body.style.overflow = '';
            navBar.style.backdropFilter = '';
            navBar.style.webkitBackdropFilter = '';
            navBar.style.background = '';
        } else {
            navLinks.classList.add('open');
            openIcon.style.display = 'none';
            closeIcon.style.display = '';
            document.body.style.overflow = 'hidden';
            navBar.style.backdropFilter = 'none';
            navBar.style.webkitBackdropFilter = 'none';
            // Dark mode background adaptation
            navBar.style.background = document.documentElement.classList.contains('dark-mode') ? '#1c1c1e' : '#ffffff';
        }
    }
    // Close mobile nav when clicking a link (unless it's a dropdown toggle)
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('#mobileNavLinks a');
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') return; // Don't close if it's a toggle
                var nav = document.getElementById('mobileNavLinks');
                if (nav && nav.classList.contains('open')) {
                    toggleMobileNav();
                }
            });
        });
    });
    </script>
    <div class="container">