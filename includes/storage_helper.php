<?php
/**
 * Unified Cloudinary Storage Helper
 * 
 * This helper allows the marketplace to store all images and media persistently in Cloudinary.
 * It replaces the old local/Supabase storage logic.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../backend/config/cloudinary.php';

/**
 * Intelligent single-entry storage function
 */
function storage_upload($tempPath, $targetFolder, $fileName, $mimeType) {
    // Construct the file array that uploadToCloudinary expects
    $file = [
        'tmp_name' => $tempPath,
        'type' => $mimeType,
        'name' => $fileName,
        'error' => 0,
        'size' => filesize($tempPath)
    ];

    // Upload to Cloudinary
    $cloudUrl = uploadToCloudinary($file, $targetFolder);
    
    if ($cloudUrl) {
        return $cloudUrl;
    }

    // Fallback to local if Cloudinary fails or isn't configured
    global $baseUrl;
    $localDir = __DIR__ . '/../uploads/' . $targetFolder;
    if (!is_dir($localDir)) mkdir($localDir, 0777, true);
    
    $localPath = $localDir . '/' . $fileName;
    if (copy($tempPath, $localPath)) {
        return 'uploads/' . $targetFolder . '/' . $fileName;
    }

    return false;
}
