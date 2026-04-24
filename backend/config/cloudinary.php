<?php
/**
 * Cloudinary Upload Helper
 */

function uploadToCloudinary(array $file, string $folder = 'marketplace'): ?string {
    $cloudName = env('CLOUDINARY_CLOUD_NAME', '');
    $apiKey = env('CLOUDINARY_API_KEY', '');
    $apiSecret = env('CLOUDINARY_API_SECRET', '');

    if (!$cloudName || !$apiKey || !$apiSecret) {
        // Fallback: save locally if Cloudinary not configured
        return uploadLocally($file, $folder);
    }

    $timestamp = time();
    $params = [
        'folder' => $folder,
        'timestamp' => $timestamp,
    ];

    // Generate signature
    ksort($params);
    $signStr = '';
    foreach ($params as $k => $v) {
        $signStr .= ($signStr ? '&' : '') . "$k=$v";
    }
    $signature = sha1($signStr . $apiSecret);

    $postData = [
        'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'signature' => $signature,
    ];

    $url = "https://api.cloudinary.com/v1_1/$cloudName/auto/upload";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        return $data['secure_url'] ?? null;
    }

    error_log("Cloudinary upload failed: $response");
    return uploadLocally($file, $folder);
}

function uploadLocally(array $file, string $folder): ?string {
    // Try Supabase Storage first (persistent across Render redeployments)
    $projectRef = env('SUPABASE_PROJECT_REF', '');
    $anonKey    = env('SUPABASE_ANON_KEY', '');
    if ($projectRef && $anonKey) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('f_') . '.' . $ext;
        $mime = $file['type'] ?: 'image/jpeg';
        $bucket = env('SUPABASE_BUCKET', 'uploads');
        $destPath = $folder . '/' . $filename;

        $url = "https://{$projectRef}.supabase.co/storage/v1/object/{$bucket}/" . ltrim($destPath, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file['tmp_name']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $anonKey",
            "Authorization: Bearer $anonKey",
            "Content-Type: $mime",
            "cache-control: max-age=3600"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 200 && $httpCode < 300) {
            return "https://{$projectRef}.supabase.co/storage/v1/object/public/{$bucket}/" . ltrim($destPath, '/');
        }
        error_log("Supabase upload fallback failed ($httpCode): $response");
    }

    // Final fallback: save to local filesystem (dev only — lost on Render redeploy)
    $uploadDir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mp3', 'wav', 'm4a', 'ogg'];
    if (!in_array($ext, $allowed)) return null;

    $filename = uniqid('f_') . '.' . $ext;
    $path = "$uploadDir/$filename";

    if (move_uploaded_file($file['tmp_name'], $path)) {
        $appUrl = env('APP_URL', '');
        if (!$appUrl) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $appUrl = "$scheme://$host";
        }
        $appUrl = rtrim($appUrl, '/');
        return "$appUrl/uploads/$folder/$filename";
    }

    return null;
}
