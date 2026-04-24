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
    // Prefer service_role key for server-side uploads (bypasses RLS)
    $serviceKey  = env('SUPABASE_SERVICE_KEY', '');
    $anonKey     = env('SUPABASE_ANON_KEY', '');
    $authKey     = $serviceKey ?: $anonKey;

    if ($projectRef && $authKey) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm'];
        if (!in_array($ext, $allowed)) {
            error_log("Supabase upload skipped: invalid extension '$ext'");
        } else {
            $filename = uniqid('f_') . '.' . $ext;
            $mime = $file['type'] ?: 'image/jpeg';
            $bucket = env('SUPABASE_BUCKET', 'uploads');
            $destPath = $folder . '/' . $filename;

            $fileContents = file_get_contents($file['tmp_name']);
            if ($fileContents === false) {
                error_log("Supabase upload failed: cannot read temp file {$file['tmp_name']}");
            } else {
                $url = "https://{$projectRef}.supabase.co/storage/v1/object/{$bucket}/" . ltrim($destPath, '/');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: $authKey",
                    "Authorization: Bearer $authKey",
                    "Content-Type: $mime",
                    "x-upsert: true",
                    "cache-control: max-age=3600"
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $curlErr  = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($curlErr) {
                    error_log("Supabase upload curl error: $curlErr");
                } elseif ($httpCode >= 200 && $httpCode < 300) {
                    $publicUrl = "https://{$projectRef}.supabase.co/storage/v1/object/public/{$bucket}/" . ltrim($destPath, '/');
                    error_log("Supabase upload OK: $publicUrl");
                    return $publicUrl;
                } else {
                    error_log("Supabase upload failed (HTTP $httpCode): $response | Bucket=$bucket Path=$destPath KeyType=" . ($serviceKey ? 'service' : 'anon'));
                }
            }
        }
    } else {
        error_log("Supabase upload skipped: missing SUPABASE_PROJECT_REF or key (ref='$projectRef')");
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
            // Render always uses HTTPS — force it
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
            $scheme = $isLocal ? 'http' : 'https';
            $appUrl = "$scheme://$host";
        }
        $appUrl = rtrim($appUrl, '/');
        error_log("Local upload fallback: $appUrl/uploads/$folder/$filename (will be lost on Render redeploy!)");
        return "$appUrl/uploads/$folder/$filename";
    }

    return null;
}
