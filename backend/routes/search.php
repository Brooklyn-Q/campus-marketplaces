<?php
/**
 * Search Routes
 * GET /search/suggest?q=... — Autocomplete suggestions
 */

// ── AUTOCOMPLETE ──
if ($method === 'GET' && ($action === 'suggest' || !$action)) {
    $q = trim(getQueryParam('q', ''));
    if (strlen($q) < 2) jsonResponse(['suggestions' => []]);

    $stmt = $pdo->prepare("
        SELECT DISTINCT p.title, p.category, p.price, 
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as image
        FROM products p
        WHERE p.status = 'approved' AND (p.title LIKE ? OR p.category LIKE ?)
        ORDER BY p.views DESC
        LIMIT 5
    ");
    $stmt->execute(["%$q%", "%$q%"]);

    jsonResponse(['suggestions' => $stmt->fetchAll()]);
}

else {
    jsonError('Search endpoint not found', 404);
}
