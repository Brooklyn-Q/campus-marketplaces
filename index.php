<?php
require_once 'includes/header.php';
require_once 'includes/ai_recommendations.php';

if (!isset($pdo)) {
    echo "<div class='glass form-container text-center'><h2>Run Setup</h2><a href='setup.php' class='btn btn-primary mt-2'>Initialize Database</a></div>";
    require_once 'includes/footer.php'; exit;
}

if (!function_exists('dedupe_items_by_id')) {
    function dedupe_items_by_id(array $items): array {
        $seen = [];
        $deduped = [];

        foreach ($items as $index => $item) {
            $key = isset($item['id']) ? (string) $item['id'] : 'fallback-' . $index;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $item;
        }

        return $deduped;
    }
}

// Database migrations moved to migrate.php for performance

// Filters
$search = trim($_GET['search'] ?? '');
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$category = $_GET['category'] ?? '';

// Fetch Context-Aware Ads (Multiple)
$homepage_ads = [];
$ad_loc = (!empty($category)) ? 'category' : 'homepage';
try {
    $stmt_ads = $pdo->prepare("SELECT * FROM ad_placements WHERE placement = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt_ads->execute([$ad_loc]);
    $homepage_ads = $stmt_ads->fetchAll();
    
    if (count($homepage_ads) > 0) {
        $ad_ids = array_column($homepage_ads, 'id');
        $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
        $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id IN ($placeholders)")->execute($ad_ids);
    }
} catch(Exception $e) {}

$homepage_ads = dedupe_items_by_id($homepage_ads);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Base query - only approved + not on vacation
// Base query
if ($search) {
    $p_join = " JOIN users u ON p.user_id = u.id ";
    $p_order = " ORDER BY FIELD(u.seller_tier, 'premium', 'pro', 'basic'), p.boosted_until DESC, p.created_at DESC ";
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.seller_tier, u.profile_pic as seller_pic,
              (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
              (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
              FROM products p $p_join WHERE p.status='approved' AND u.vacation_mode = 0 AND (p.title LIKE ? OR p.category LIKE ? OR p.description LIKE ?) $p_order");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $products = $stmt->fetchAll();
    
    // TYPO-TOLERANT FALLBACK
    if (count($products) === 0 && strlen($search) > 3) {
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.seller_tier, u.profile_pic as seller_pic,
              (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
              (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
              FROM products p $p_join WHERE p.status='approved' AND u.vacation_mode = 0 AND (SOUNDEX(p.title) = SOUNDEX(?) OR p.title LIKE ?) $p_order LIMIT 10");
        $stmt->execute([$search, substr($search, 0, 4) . "%"]);
        $products = $stmt->fetchAll();
    }
    $total = count($products);
    $total_pages = 1;
} else {
    $seller_filter = trim($_GET['seller'] ?? '');

    $vacation_check = "u.vacation_mode = " . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'false' : '0');
    $query = "SELECT p.*, u.username, u.seller_tier, u.profile_pic as seller_pic,
              (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image,
              (SELECT dr.original_price FROM discount_requests dr WHERE dr.product_id = p.id AND dr.status = 'approved' ORDER BY dr.created_at DESC LIMIT 1) as original_price_before_discount
              FROM products p
              JOIN users u ON p.user_id = u.id
              WHERE p.status = 'approved' AND $vacation_check";
    $count_query = "SELECT COUNT(*) FROM products p JOIN users u ON p.user_id = u.id WHERE p.status = 'approved' AND $vacation_check";
    $params = [];

    if ($min_price !== '') { $query .= " AND p.price >= ?"; $count_query .= " AND p.price >= ?"; $params[] = $min_price; }
    if ($max_price !== '') { $query .= " AND p.price <= ?"; $count_query .= " AND p.price <= ?"; $params[] = $max_price; }
    if ($category)         { $query .= " AND p.category = ?"; $count_query .= " AND p.category = ?"; $params[] = $category; }
    if ($seller_filter)    { $query .= " AND u.username = ?"; $count_query .= " AND u.username = ?"; $params[] = $seller_filter; }

    $order_sql = "CASE u.seller_tier WHEN 'premium' THEN 1 WHEN 'pro' THEN 2 WHEN 'basic' THEN 3 ELSE 4 END ASC";
    $query .= " ORDER BY $order_sql, (p.boosted_until > NOW()) DESC, p.created_at DESC LIMIT $per_page OFFSET $offset";

    $stmt = $pdo->prepare($count_query); $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));

    $stmt = $pdo->prepare($query); $stmt->execute($params);
    $products = $stmt->fetchAll();
}

$products = dedupe_items_by_id($products);

// Categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status='approved' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<?php 
$cat_images = [
    'Computer & Accessories' => 'IMG_5825.webp',
    'Phone & Accessories' => 'IMG_5822.webp',
    'Electrical Appliances' => 'IMG_5827.webp',
    'Fashion' => 'IMG_5828.webp',
    'Food & Groceries' => 'IMG_5830.webp',
    'Education & Books' => 'IMG_5831.webp',
    'Hostels for Rent' => 'IMG_5833.webp'
];
$cat_descriptions = [
    'Computer & Accessories' => 'Laptops, monitors, and all computing essentials.',
    'Phone & Accessories' => 'Smartphones, covers, screen protectors, and mobile gear.',
    'Electrical Appliances' => 'Home and dorm gadgets, microwaves, fans, and blenders.',
    'Fashion' => 'Trendy clothing, shoes, bags, and stylish accessories.',
    'Food & Groceries' => 'Snacks, beverages, fresh produce, and daily provisions.',
    'Education & Books' => 'Textbooks, notebooks, stationery, and study materials.',
    'Hostels for Rent' => 'Accommodation, shared rooms, apartments, and living spaces.'
];
$cat_img = ($category && isset($cat_images[$category])) ? $cat_images[$category] : null;
$cat_desc = ($category && isset($cat_descriptions[$category])) ? $cat_descriptions[$category] : 'Explore what this category comprises of.';
$is_default_home = empty($search) && empty($category) && empty($min_price) && empty($max_price) && $page == 1;

$ai_suggestions = [];
$display_products = $products;
if ($is_default_home) {
    $ai_suggestions = get_smart_suggestions($pdo, 'home', $_SESSION['recent_views'] ?? [], 6, true);
    $ai_suggestions = dedupe_items_by_id($ai_suggestions);

    // Keep homepage sections consistent by preventing the same product
    // from showing in both Recommendations and the main product grid.
    $recommended_ids = [];
    foreach ($ai_suggestions as $rec) {
        if (isset($rec['id'])) {
            $recommended_ids[(string)$rec['id']] = true;
        }
    }
    if (!empty($recommended_ids)) {
        $display_products = array_values(array_filter($products, function ($product) use ($recommended_ids) {
            if (!isset($product['id'])) {
                return true;
            }
            return !isset($recommended_ids[(string)$product['id']]);
        }));
    }
}
?>
<?php if ($is_default_home): ?>
    </div><!-- End container for full bleed -->
    <!-- REACT HERO (Only show on default home) -->
    <div id="react-hero-root" data-react-mount="hero">
        <!-- PHP FALLBACK HERO — replaced by React when JS loads -->
        <noscript><style>.php-hero-fallback{display:block !important;}</style></noscript>
        <div class="php-hero-fallback" id="phpHeroFallback">
            <!-- Video Hero -->
            <div style="position:relative; width:100%; height:100vh; display:flex; flex-direction:column; justify-content:flex-start; align-items:center; overflow:hidden; background:linear-gradient(135deg, #0071e3 0%, #34aaff 100%);">
                <video autoplay loop muted playsinline poster="<?= getAssetUrl('assets/dist/IMG_5825.webp') ?>" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center;">
                    <source src="<?= getAssetUrl('assets/dist/hero.mp4') ?>" type="video/mp4">
                </video>
                <!-- Gradient overlay -->
                <div style="position:absolute; inset:0; z-index:1; background:linear-gradient(to bottom, rgba(0,0,0,0.62) 0%, rgba(0,0,0,0.28) 55%, rgba(0,0,0,0.55) 100%);"></div>
                <!-- Bottom fade -->
                <div style="position:absolute; left:0; right:0; bottom:0; height:12rem; z-index:2; background:linear-gradient(to top, var(--bg, #fff), transparent);"></div>
                <!-- Content -->
                <div style="position:relative; z-index:10; display:flex; flex-direction:column; align-items:center; text-align:center; padding:0 1.5rem; width:100%; max-width:56rem; padding-top:14vh;">
                    <h1 style="font-size:clamp(2.4rem, 6vw, 4rem); font-weight:800; color:#fff; letter-spacing:-0.04em; line-height:1.05; margin-bottom:1rem; font-family:'Inter', -apple-system, BlinkMacSystemFont, sans-serif;">
                        Campus Marketplace
                    </h1>
                    <p style="font-size:clamp(2.5rem, 7vw, 5rem); font-weight:900; letter-spacing:-0.04em; background:linear-gradient(105deg, #ffffff 0%, #a0d4ff 50%, #ffffff 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1.0; margin-bottom:0.6rem; font-family:'Inter', -apple-system, BlinkMacSystemFont, sans-serif;">
                        Buy &amp; Sell
                    </p>
                    <p style="font-size:clamp(0.95rem, 2.2vw, 1.15rem); color:rgba(255,255,255,0.72); margin-bottom:2.5rem; font-weight:400; letter-spacing:0.01em; font-family:'Inter', sans-serif;">
                        Everything You Need on Campus
                    </p>
                    <!-- Search Bar -->
                    <form action="index.php" method="GET" style="width:100%; max-width:36rem;">
                        <div style="display:flex; align-items:center; overflow:hidden; background:rgba(255,255,255,0.12); backdrop-filter:saturate(200%) blur(28px); -webkit-backdrop-filter:saturate(200%) blur(28px); border:1px solid rgba(255,255,255,0.22); border-radius:999px; box-shadow:0 8px 32px rgba(0,0,0,0.18), inset 0 1px 1px rgba(255,255,255,0.15);">
                            <div style="padding-left:1.25rem; color:rgba(255,255,255,0.6);">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>
                            <input type="text" name="search" placeholder="Search products, textbooks, phones..."
                                style="flex:1; background:transparent; border:none; color:#fff; padding:1rem 0.75rem; font-weight:500; font-size:0.9rem; outline:none;">
                            <div style="padding-right:0.35rem;">
                                <button type="submit" style="background:#0071e3; color:#fff; border:none; padding:0.6rem 1.4rem; border-radius:999px; font-weight:600; font-size:0.85rem; cursor:pointer;">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categories Section -->
            <div style="text-align:center; padding:2.5rem 1.5rem 1.5rem;">
                <p style="color:#0071e3; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.16em; font-weight:700; margin-bottom:0.7rem;">Categories</p>
                <h2 style="font-size:clamp(2rem, 5vw, 3rem); font-weight:800; letter-spacing:-0.03em; line-height:1.1;">Browse by category.</h2>
            </div>
            <div style="display:flex; overflow-x:auto; gap:1rem; padding:0 1rem 2rem; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch;">
                <?php
                $hero_cats = [
                    ['title' => 'Computer & Accessories', 'image' => 'IMG_5825.webp'],
                    ['title' => 'Phone & Accessories',    'image' => 'IMG_5822.webp'],
                    ['title' => 'Electrical Appliances',  'image' => 'IMG_5827.webp'],
                    ['title' => 'Fashion',                'image' => 'IMG_5828.webp'],
                    ['title' => 'Food & Groceries',       'image' => 'IMG_5830.webp'],
                    ['title' => 'Education & Books',      'image' => 'IMG_5831.webp'],
                    ['title' => 'Hostels for Rent',       'image' => 'IMG_5833.webp'],
                ];
                foreach($hero_cats as $hc):
                ?>
                <a href="index.php?category=<?= urlencode($hc['title']) ?>" style="flex:0 0 80vw; max-width:350px; scroll-snap-align:center; text-decoration:none; display:block;">
                    <div style="position:relative; width:100%; height:350px; border-radius:16px; overflow:hidden;">
                        <img src="<?= getAssetUrl('assets/dist/' . $hc['image']) ?>" alt="<?= htmlspecialchars($hc['title']) ?>" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 40%, transparent 100%);"></div>
                        <div style="position:absolute; bottom:0; left:0; right:0; padding:1.5rem; text-align:left;">
                            <h3 style="font-size:1.35rem; font-weight:700; color:#fff; letter-spacing:-0.01em; margin:0 0 0.25rem;"><?= htmlspecialchars($hc['title']) ?></h3>
                            <p style="color:#0071e3; font-size:0.875rem; font-weight:500; margin:0;">Explore →</p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
        // Hide PHP fallback once React mounts the hero
        (function() {
            var observer = new MutationObserver(function(mutations) {
                var root = document.getElementById('react-hero-root');
                if (root && root.getAttribute('data-react-mounted') === 'true') {
                    var fallback = document.getElementById('phpHeroFallback');
                    if (fallback) fallback.style.display = 'none';
                    observer.disconnect();
                }
            });
            var root = document.getElementById('react-hero-root');
            if (root) {
                if (root.getAttribute('data-react-mounted') === 'true') {
                    var fb = document.getElementById('phpHeroFallback');
                    if (fb) fb.style.display = 'none';
                } else {
                    observer.observe(root, { attributes: true });
                }
            }
        })();
    </script>
    <div class="container"><!-- Reopen container for the rest of the page -->
<?php else: ?>
    <?php if($cat_img): ?>
        </div>
        <!-- Full Width Category Banner -->
        <div style="position:relative; width:100%; height:40vh; min-height:300px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:2rem;">
            <img src="<?= getAssetUrl(htmlspecialchars($cat_img)) ?>" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center top; z-index:0;">
            <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5); z-index:1;"></div>
            <div style="text-align:center; z-index:2; padding:0 20px;">
                <h1 style="font-size:3.5rem; font-weight:800; color:#fff; letter-spacing:-0.04em; text-shadow:0 12px 40px rgba(0,0,0,0.8);"><?= htmlspecialchars($category) ?></h1>
                <p style="color:#f5f5f7; font-size:1.2rem; margin-top:0.5rem; font-weight:500;"><?= htmlspecialchars($cat_desc) ?></p>
                <p style="color:#f5f5f7; font-size:1rem; margin-top:0.5rem; font-weight:400;">Found <?= $total ?> products</p>
            </div>
        </div>
        <div class="container">
    <?php else: ?>
        <!-- Search Results Header (Apple Style) -->
        <div style="padding:5rem 5% 3rem; background:linear-gradient(to bottom, rgba(0,113,227,0.06), transparent); border-bottom:1px solid rgba(0,0,0,0.05); text-align:center; margin-bottom:2rem;">
            <h1 style="font-size:3.5rem; font-weight:800; color:var(--text-main); letter-spacing:-0.04em;">
                <?php 
                    if ($search) echo 'Search: "'.htmlspecialchars($search).'"';
                    else echo 'All Products';
                ?>
            </h1>
            <p style="color:var(--text-muted); font-size:1.2rem; margin-top:0.5rem; font-weight:500;">Found <?= $total ?> items</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- FRAUD NOTICE -->
<div class="notice-box-inline mb-4" style="max-width:800px; margin-left:auto; margin-right:auto; background:rgba(255,204,0,0.15); border:1px solid rgba(255,204,0,0.5); padding:15px; border-radius:12px; text-align:center; margin-top:20px;">
    <strong><span style="font-size:1.1rem; vertical-align:middle; margin-right:5px;">⚠️</span> SAFETY NOTICE:</strong>
    <span>All transactions should be made in person. Sending money online without meeting the seller is at your own risk. Campus Marketplace will not be held accountable for online transactions.</span>
</div>

<!-- FILTERS -->
<div class="glass filter-bar">
    <form action="index.php" method="GET" style="display:flex; gap:0.75rem; width:100%; flex-wrap:wrap; align-items:center;" id="searchForm">
        <div style="flex:1; min-width:180px; position:relative;">
            <input type="text" name="search" id="searchInput" class="form-control" autocomplete="off" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" style="width:100%;">
            <div id="searchSuggestions" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:9999; max-height:200px; overflow-y:auto; backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);"></div>
        </div>
        <select name="category" class="form-control" style="max-width:160px;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="min_price" class="form-control" placeholder="Min ₵" value="<?= htmlspecialchars($min_price) ?>" style="width:100px;">
        <input type="number" name="max_price" class="form-control" placeholder="Max ₵" value="<?= htmlspecialchars($max_price) ?>" style="width:100px;">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</div>

<!-- AD CAROUSEL (Context Aware) -->
<?php if(count($homepage_ads) > 0): ?>
<div style="margin-top:2rem; margin-bottom:1.5rem; position:relative;">
    <div id="adCarousel" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 10px 0;">
        <?php foreach($homepage_ads as $ad): ?>
        <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener" onclick="fetch('ad_click.php?id=<?= $ad['id'] ?>')" class="fade-in ad-item-link" style="flex: 0 0 100%; scroll-snap-align: start; min-width: 100%; text-decoration: none;">
            <div class="ad-image-container" style="border-radius:24px; overflow:hidden; border:1px solid rgba(0,0,0,0.05); position:relative; transition:all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
                <?php if($ad['image_url']): ?>
                    <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="ad-banner-img" loading="lazy" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    <div style="background:linear-gradient(135deg, #0071e3, #34aaff); color:#fff; padding:2.5rem; text-align:center; min-height:160px; display:flex; flex-direction:column; justify-content:center;">
                        <p style="font-size:0.7rem; letter-spacing:0.15em; text-transform:uppercase; opacity:0.8; margin-bottom:0.5rem; font-weight:700;">Sponsored Content</p>
                        <p style="font-size:1.5rem; font-weight:800; letter-spacing:-0.02em;"><?= htmlspecialchars($ad['title']) ?></p>
                    </div>
                <?php endif; ?>
                <span style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,0.4); backdrop-filter:blur(10px); color:#fff; font-size:0.6rem; padding:4px 10px; border-radius:8px; letter-spacing:0.08em; font-weight:700; border:1px solid rgba(255,255,255,0.1);">AD</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Navigation dots for ads if more than 1 -->
    <?php if(count($homepage_ads) > 1): ?>
    <div style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:10; background:rgba(0,0,0,0.25); padding:4px 10px; border-radius:10px; backdrop-filter:blur(8px);">
        <?php foreach($homepage_ads as $idx => $ad): ?>
            <div class="ad-dot" style="width:6px; height:6px; border-radius:50%; background:<?= $idx === 0 ? '#fff' : 'rgba(255,255,255,0.4)' ?>; transition:all 0.2s;"></div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const carousel = document.getElementById('adCarousel');
            if (!carousel || carousel.dataset.initialized === 'true') return;
            carousel.dataset.initialized = 'true';

            const dots = Array.from(document.querySelectorAll('.ad-dot'));
            const totalSlides = <?= count($homepage_ads) ?>;
            let currentIndex = 0;
            let scrollInterval = null;
            let ticking = false;

            const updateDots = (idx) => {
                dots.forEach((dot, i) => {
                    dot.style.background = (i === idx) ? '#fff' : 'rgba(255,255,255,0.4)';
                    dot.style.width = (i === idx) ? '12px' : '6px';
                    dot.style.borderRadius = (i === idx) ? '3px' : '50%';
                });
            };

            const stopAutoScroll = () => {
                if (scrollInterval) {
                    clearInterval(scrollInterval);
                    scrollInterval = null;
                }
            };

            const startAutoScroll = () => {
                if (scrollInterval || totalSlides <= 1) return;

                scrollInterval = setInterval(() => {
                    if (document.hidden) return;

                    if (currentIndex >= totalSlides - 1) {
                        stopAutoScroll();
                        return;
                    }

                    currentIndex += 1;
                    carousel.scrollTo({
                        left: currentIndex * carousel.offsetWidth,
                        behavior: 'smooth'
                    });
                    updateDots(currentIndex);
                }, 6000);
            };

            carousel.addEventListener('scroll', () => {
                if (ticking) return;

                ticking = true;
                window.requestAnimationFrame(() => {
                    currentIndex = Math.round(carousel.scrollLeft / Math.max(carousel.offsetWidth, 1));
                    updateDots(currentIndex);
                    ticking = false;
                });
            }, { passive: true });

            carousel.addEventListener('mouseenter', stopAutoScroll);
            carousel.addEventListener('mouseleave', startAutoScroll);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stopAutoScroll();
                else startAutoScroll();
            });

            updateDots(0);
            startAutoScroll();
        });
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    let timeoutId;

    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim();
        clearTimeout(timeoutId);
        
        if (q.length < 2) {
            searchSuggestions.style.display = 'none';
            return;
        }

        timeoutId = setTimeout(() => {
            fetch(`${window.MARKETPLACE_BASE_URL || '/'}api/search_suggest.php?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    const suggestions = Array.isArray(data) ? data : (data.suggestions || []);
                    if (suggestions.length > 0) {
                        searchSuggestions.innerHTML = '';
                        suggestions.forEach(word => {
                            const label = typeof word === 'string' ? word : (word.title || word.category || '');
                            if (!label) return;
                            const wordDiv = document.createElement('div');
                            wordDiv.textContent = label;
                            wordDiv.style.cssText = 'padding:10px 16px; cursor:pointer; font-weight:500; border-bottom:1px solid rgba(0,0,0,0.05); transition:background 0.2s;';
                            wordDiv.onmouseover = () => wordDiv.style.background = 'rgba(0,113,227,0.06)';
                            wordDiv.onmouseout = () => wordDiv.style.background = 'transparent';
                            wordDiv.onclick = () => {
                                searchInput.value = label;
                                searchSuggestions.style.display = 'none';
                                document.getElementById('searchForm').submit();
                            };
                            searchSuggestions.appendChild(wordDiv);
                        });
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                }).catch(err => { console.error('Suggestion Error:', err); searchSuggestions.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });
});
</script>

<h2 class="mb-2" style="font-size:1.3rem;">Approved Listings <span class="text-muted" style="font-size:0.9rem;">(<?= $total ?> items)</span></h2>

<?php if ($is_default_home): ?>
    <?php if(count($ai_suggestions) > 0): ?>
    <div style="margin-bottom:2rem; position:relative;">
        <h3 class="mb-3" style="font-size:1.4rem; font-weight:800; display:flex; align-items:center; justify-content:space-between;">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#ai-grad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <defs><linearGradient id="ai-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0071e3"/><stop offset="100%" stop-color="#34aaff"/></linearGradient></defs>
                    <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                </svg>
                Recommendations
            </span>
            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Swipe →</span>
        </h3>
        
        <div id="aiRecScroll" class="horizontal-scroll-container" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;">
            <?php foreach($ai_suggestions as $mp): ?>
                <a href="product.php?id=<?= $mp['id'] ?>" class="scroll-card glass fade-in" style="scroll-snap-align: start; min-width: 160px;">
                    <div class="product-img-wrap" style="aspect-ratio: 4/3; max-height: 140px; border-radius: 12px; overflow:hidden;">
                        <?php if($mp['main_image']): ?>
                            <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($mp['main_image'])) ?>" alt="<?= htmlspecialchars($mp['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.1);">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body" style="padding:10px 6px;">
                        <p class="product-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.8rem; font-weight:700; margin:0;"><?= htmlspecialchars($mp['title']) ?></p>
                        <p class="product-price" style="font-size:0.9rem; font-weight:800; color:var(--primary); margin-top:2px;">₵<?= number_format($mp['price'], 2) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const s = document.getElementById('aiRecScroll');
                if (!s || s.dataset.initialized === 'true') return;
                s.dataset.initialized = 'true';

                let auto = null;

                const stopAuto = () => {
                    if (auto) {
                        clearInterval(auto);
                        auto = null;
                    }
                };

                const startAuto = () => {
                    if (auto) return;

                    auto = setInterval(() => {
                        if (document.hidden) return;

                        const nextLeft = s.scrollLeft + 188;
                        const maxLeft = s.scrollWidth - s.clientWidth;

                        if (nextLeft >= maxLeft - 10) {
                            stopAuto();
                            return;
                        }

                        s.scrollTo({ left: nextLeft, behavior: 'smooth' });
                    }, 2500);
                };

                s.addEventListener('mouseenter', stopAuto);
                s.addEventListener('mouseleave', startAuto);
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stopAuto();
                    else startAuto();
                });

                startAuto();
            });
        </script>
    </div>
    
    <?php endif; ?>
<?php endif; ?>


<div class="product-grid">
    <?php if (count($display_products) > 0): ?>
        <?php foreach($display_products as $p): ?>
            <a href="product.php?id=<?= $p['id'] ?>" class="glass product-card fade-in" style="flex-direction:column;">
                <div class="product-img-wrap" style="aspect-ratio: 1 / 1; border-radius: 14px; overflow:hidden;">
                    <?php if($p['main_image']): ?>
                        <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($p['main_image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="product-img" loading="lazy" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#555;background:rgba(0,0,0,0.3);">No Image</div>
                    <?php endif; ?>

                    <?php if($p['boosted_until'] && strtotime($p['boosted_until']) > time()): ?>
                        <span class="boosted-badge">⚡ Boosted</span>
                        <span class="featured-badge">⭐ Featured</span>
                    <?php endif; ?>
                    <?php
                        $promo = isset($p['promo_tag']) ? trim($p['promo_tag']) : '';
                    ?>
                    <?php if($promo): ?>
                    <span class="boosted-badge" style="top:8px; bottom:auto; left:8px; background:rgba(0,0,0,0.75); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); color:#fff; font-size:0.6rem; padding:0.3rem 0.6rem; border-radius:8px; font-weight:600; box-shadow:0 2px 8px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.15); display:flex; align-items:center; gap:4px; letter-spacing:0.02em;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        <?= htmlspecialchars(str_replace(['⚡ ', '⏳ ', '🎓 ', '📦 ', '🏷️ '], '', $promo)) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <p class="product-title" style="font-size:0.75rem;"><?= htmlspecialchars($p['title']) ?></p>
                    <?php if(!empty($p['original_price_before_discount']) && $p['original_price_before_discount'] > $p['price']): ?>
                        <p class="product-price">
                            <span style="text-decoration:line-through; opacity:0.5; font-size:0.75rem; font-weight:400;">₵<?= number_format($p['original_price_before_discount'], 2) ?></span>
                            ₵<?= number_format($p['price'], 2) ?>
                            <span style="background:rgba(239,68,68,0.12); color:#ef4444; font-size:0.55rem; font-weight:700; padding:0.15rem 0.35rem; border-radius:4px; margin-left:2px;">
                                −<?= round(100 - ($p['price'] / $p['original_price_before_discount'] * 100)) ?>%
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="product-price">₵<?= number_format($p['price'], 2) ?></p>
                    <?php endif; ?>
                    <p class="product-meta">
                        By <?= htmlspecialchars($p['username']) ?>
                        <?= getBadgeHtml($pdo, $p['seller_tier'] ?? 'basic') ?>
                    </p>

                    <?php if($p['quantity'] <= 0): ?>
                        <span style="display:block; font-size:0.65rem; color:#ef4444; font-weight:700; margin-top:0.2rem;">🚫 Out of Stock</span>
                    <?php elseif($p['quantity'] <= 5): ?>
                        <span style="display:block; font-size:0.65rem; color:#ff9500; font-weight:700; margin-top:0.2rem;">🔥 Only <?= $p['quantity'] ?> left!</span>
                    <?php endif; ?>
                    <?php if($p['quantity'] > 0): ?>
                    <?php 
                        $cardImg = $p['main_image'] ? getAssetUrl('uploads/'.htmlspecialchars($p['main_image'])) : '';
                        $cardJsName = json_encode($p['title']);
                    ?>
                    <button
                        type="button"
                        class="quick-add-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); cmCart.add(<?= $p['id'] ?>, <?= $cardJsName ?>, <?= $p['price'] ?>, '<?= $cardImg ?>'); var _b=this; _b.textContent='✓ Added'; setTimeout(function(){_b.textContent='+ Add';}, 1500);"
                    >+ Add</button>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No products found matching your criteria.</p>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if($total_pages > 1): ?>
<div class="flex gap-1 mt-3" style="justify-content:center;">
    <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&min_price=<?= urlencode($min_price) ?>&max_price=<?= urlencode($max_price) ?>"
           class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
