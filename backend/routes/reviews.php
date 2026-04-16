<?php
/**
 * Reviews Route (standalone)
 * GET /reviews?product_id=5 — Get reviews for a product
 */

if ($method === 'GET') {
    $productId = (int) getQueryParam('product_id', 0);
    if (!$productId) jsonError('product_id is required');

    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.profile_pic 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$productId]);

    jsonResponse([
        'reviews' => $stmt->fetchAll(),
        'avg_rating' => getAvgRating($pdo, $productId),
    ]);
}

else {
    jsonError('Reviews endpoint not found', 404);
}
