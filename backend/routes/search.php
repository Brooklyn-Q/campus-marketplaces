<?php
/**
 * Search Routes
 * GET /search/suggest?q=... — Autocomplete suggestions
 */

// ── AUTOCOMPLETE ──
if ($method === 'GET' && ($action === 'suggest' || !$action)) {
    $q = trim(getQueryParam('q', ''));
    if (strlen($q) < 2) jsonResponse(['suggestions' => []]);

    $vacationCheck = sqlBool(false, $pdo);
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.price, p.category,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as image
        FROM products p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'approved' AND u.vacation_mode = $vacationCheck
        AND (p.title $likeOperator ? OR p.description $likeOperator ? OR p.category $likeOperator ?)
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);

    jsonResponse(['suggestions' => $stmt->fetchAll()]);
}

else {
    jsonError('Search endpoint not found', 404);
}
