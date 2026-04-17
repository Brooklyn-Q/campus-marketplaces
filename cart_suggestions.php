<?php
require_once 'includes/db.php';
require_once 'includes/ai_recommendations.php';
check_csrf();

$raw = file_get_contents('php://input');
$items = json_decode($raw, true);

if (!$items || !is_array($items)) {
    // Fallback if no cart data
    $suggestions = get_smart_suggestions($pdo, 'home', $_SESSION['recent_views'] ?? [], 4);
} else {
    $suggestions = get_smart_suggestions($pdo, 'cart', $items, 4);
}

if (count($suggestions) > 0) {
    echo '<h3 class="mb-3" style="font-size:1.2rem; display:flex; align-items:center; gap:0.5rem; margin-top:2rem;">';
    echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="url(#ai-grad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><defs><linearGradient id="ai-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0071e3"/><stop offset="100%" stop-color="#34aaff"/></linearGradient></defs><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path></svg>';
    echo 'Suggested Items Before Checkout';
    echo '</h3>';
    echo '<div class="product-grid" style="grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:1rem;">';
    foreach($suggestions as $sp) {
        $img = $sp['main_image'] ? getAssetUrl('uploads/'.htmlspecialchars($sp['main_image'])) : '';
        $img_fallback = '<div class="product-img-fallback" style="display:flex;align-items:center;justify-content:center;background:#f5f5f7;width:100%;height:100%;color:#86868b;"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><path d="m21 15-5-5L5 21"/></svg></div>';
        $img_tag = $img ? '<img src="'.$img.'" class="product-img" style="aspect-ratio:1/1;object-fit:cover;width:100%;height:100%;" loading="lazy" onerror="this.parentElement.innerHTML=\''.$img_fallback.'\';">' : $img_fallback;

        echo '
        <a href="product.php?id='.$sp['id'].'" class="glass product-card fade-in" style="background:rgba(255,255,255,0.8); text-decoration:none; color:inherit;">
            <div class="product-img-wrap" style="aspect-ratio:1/1; border-radius:12px; overflow:hidden;">
                ' . $img_tag . '
            </div>
            <div class="product-body" style="padding:8px 4px;">
                <p class="product-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.85rem; font-weight:700;">'.htmlspecialchars($sp['title']).'</p>
                <p class="product-price" style="font-size:0.9rem; color:var(--primary); font-weight:800;">₵'.number_format($sp['price'], 2).'</p>
            </div>
        </a>';
    }
    echo '</div>';
}
?>
