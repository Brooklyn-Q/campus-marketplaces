<?php
/**
 * Supabase Storage REST Helper
 * 
 * This helper allows the marketplace to store images persistently in Supabase Storage.
 * It automatically handles local vs production storage based on environment variables.
 */

require_once __DIR__ . '/db.php';

function uploadToCloud($localFilePath, $destinationPath, $mimeType = 'image/jpeg') {
    $projectRef = env('SUPABASE_PROJECT_REF');
    $anonKey    = env('SUPABASE_ANON_KEY');
    $bucket     = env('SUPABASE_BUCKET', 'uploads');

    if (!$projectRef || !$anonKey) {
        // Fallback to local storage if credentials missing
        return 'uploads/' . ltrim($destinationPath, '/');
    }

    $url = "https://{$projectRef}.supabase.co/storage/v1/object/{$bucket}/" . ltrim($destinationPath, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($localFilePath));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $anonKey",
        "Authorization: Bearer $anonKey",
        "Content-Type: $mimeType",
        "cache-control: max-age=3600"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        // Success! Return the PUBLIC URL
        return "https://{$projectRef}.supabase.co/storage/v1/object/public/{$bucket}/" . ltrim($destinationPath, '/');
    } else {
        error_log("Supabase Upload Error ($httpCode): " . $response);
        return false;
    }
}

/**
 * Intelligent single-entry storage function
 */
function storage_upload($tempPath, $targetFolder, $fileName, $mimeType) {
    global $baseUrl;
    
    // In production/Supabase mode
    if (env('DB_TYPE') === 'pgsql' || env('SUPABASE_PROJECT_REF')) {
        $dest = $targetFolder . '/' . $fileName;
        $cloudUrl = uploadToCloud($tempPath, $dest, $mimeType);
        if ($cloudUrl) return $cloudUrl;
    }

    // Fallback to local
    $localDir = __DIR__ . '/../uploads/' . $targetFolder;
    if (!is_dir($localDir)) mkdir($localDir, 0777, true);
    
    $localPath = $localDir . '/' . $fileName;
    if (move_uploaded_file($tempPath, $localPath)) {
        return 'uploads/' . $targetFolder . '/' . $fileName;
    }

    return false;
}
