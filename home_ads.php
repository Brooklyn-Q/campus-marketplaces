<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$placement = trim($_GET['placement'] ?? 'homepage');
if ($placement === '') {
    $placement = 'homepage';
}

$allowedPlacements = ['homepage', 'category', 'product'];
if (!in_array($placement, $allowedPlacements, true)) {
    http_response_code(400);
    echo json_encode(['ads' => [], 'error' => 'Invalid placement']);
    exit;
}

try {
    $isPg = ($db_type ?? '') === 'pgsql';
    $boolTrue = $isPg ? 'true' : '1';

    $stmt = $pdo->prepare("
        SELECT id, title, image_path, link_url, placement
        FROM ad_placements
        WHERE placement = ? AND is_active = $boolTrue
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$placement]);
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ads as &$ad) {
        $ad['image_url'] = $ad['image_path'] ?? '';
    }
    unset($ad);

    if (!empty($ads)) {
        $adIds = array_column($ads, 'id');
        $placeholders = implode(',', array_fill(0, count($adIds), '?'));
        $impressionStmt = $pdo->prepare("UPDATE ad_placements SET impressions = impressions + 1 WHERE id IN ($placeholders)");
        $impressionStmt->execute($adIds);
    }

    echo json_encode(['ads' => $ads]);
} catch (Throwable $e) {
    error_log('Home ads endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ads' => []]);
}
