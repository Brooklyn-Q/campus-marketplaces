<?php
/**
 * Ad Placement Routes
 * GET /ads?placement=homepage — Get active ads
 * POST /ads/:id/click — Track ad click
 */

if ($method === 'GET' && !$action) {
    $placement = getQueryParam('placement', 'homepage');

    $driver = getenv('DB_DRIVER') ?: 'mysql';
    $randSql = $driver === 'pgsql' ? "RANDOM()" : "RAND()";
    $boolTrue = sqlBool(true, $pdo);

    $stmt = $pdo->prepare("SELECT id, title, image_path, link_url, placement FROM ad_placements WHERE is_active = $boolTrue AND placement = ? ORDER BY $randSql");
    $stmt->execute([$placement]);
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize: frontend expects `image_url`, DB column is `image_path`
    foreach ($ads as &$ad) {
        $ad['image_url'] = $ad['image_path'] ?? '';
    }
    unset($ad);

    // Track impressions
    foreach ($ads as $ad) {
        $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id = ?")->execute([$ad['id']]);
    }

    jsonResponse(['ads' => $ads]);
}

elseif ($method === 'POST' && is_numeric($action) && $param === 'click') {
    $pdo->prepare("UPDATE ad_placements SET clicks = clicks + 1 WHERE id = ?")->execute([(int)$action]);
    jsonSuccess('Click tracked');
}

else {
    jsonError('Ads endpoint not found', 404);
}
