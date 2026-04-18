<?php
/**
 * Admin Ads Manager Route
 * GET /admin/ads            - List all ads
 * POST /admin/ads           - Create new ad (multipart/form-data supported)
 * PUT /admin/ads/:id/toggle - Toggle active status
 * DELETE /admin/ads/:id     - Delete ad
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

require_once __DIR__ . '/../../includes/storage_helper.php';

$adId = (int) ($segments[2] ?? 0);
$subAction = $segments[3] ?? '';

// GET: List all ads
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM ad_placements ORDER BY created_at DESC");
    $ads = $stmt->fetchAll();
    jsonResponse(['success' => true, 'ads' => $ads]);
}

// POST: Create Ad
elseif ($method === 'POST' && !$adId) {
    $body = !empty($_POST) ? $_POST : getJsonBody();
    
    $title = trim($body['ad_title'] ?? '');
    if (!$title) {
        jsonError('Ad title is required', 400);
    }

    $image_url = trim($body['ad_image'] ?? '');
    $link = trim($body['ad_link'] ?? '#');
    $placement = trim($body['ad_placement'] ?? 'homepage');

    // Handle File Upload if provided
    if (!empty($_FILES['ad_file']['name']) && $_FILES['ad_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['ad_file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg','jpeg','png','webp','gif'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['ad_file']['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (in_array($ext, $allowedExts) && in_array($mimeType, $allowedMimes)) {
            $fname = 'ad_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $storedPath = storage_upload($_FILES['ad_file']['tmp_name'], 'ads', $fname, $mimeType);
            if ($storedPath) {
                $image_url = $storedPath; // Store cloud URL
            } else {
                jsonError('Failed to upload image.', 500);
            }
        } else {
            jsonError('Invalid image format or corrupted file.', 400);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO ad_placements (title, image_url, link_url, placement) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $image_url, $link, $placement]);

    jsonResponse(['success' => true, 'message' => 'Ad created successfully']);
}

// PUT: Toggle active
elseif ($method === 'PUT' && $adId && $subAction === 'toggle') {
    $pdo->prepare("UPDATE ad_placements SET is_active = NOT is_active WHERE id = ?")->execute([$adId]);
    jsonResponse(['success' => true, 'message' => 'Ad toggled']);
}

// DELETE: Delete Ad
elseif ($method === 'DELETE' && $adId) {
    $pdo->prepare("DELETE FROM ad_placements WHERE id = ?")->execute([$adId]);
    jsonResponse(['success' => true, 'message' => 'Ad deleted']);
}

else {
    jsonError('Ads endpoint not found or unsupported method', 404);
}
