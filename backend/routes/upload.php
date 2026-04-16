<?php
/**
 * Upload Route
 * POST /upload — General file upload to Cloudinary
 */

require_once __DIR__ . '/../config/cloudinary.php';

if ($method !== 'POST') jsonError('Method not allowed', 405);

$auth = authenticate();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file uploaded');
}

$folder = $_POST['folder'] ?? 'marketplace/general';
$valErr = validateMediaFile($_FILES['file']);
if ($valErr) jsonError($valErr);

$url = uploadToCloudinary($_FILES['file'], $folder);
if (!$url) jsonError('Upload failed', 500);

jsonResponse([
    'success' => true,
    'url' => $url,
    'type' => getMediaType($_FILES['file']['name']),
]);
