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
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        return $data['secure_url'] ?? null;
    }

    error_log("Cloudinary upload failed: $response");
    return uploadLocally($file, $folder);
}

function uploadLocally(array $file, string $folder): ?string {
    $uploadDir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mp3', 'wav', 'm4a', 'ogg'];
    if (!in_array($ext, $allowed)) return null;

    $filename = uniqid('f_') . '.' . $ext;
    $path = "$uploadDir/$filename";

    if (move_uploaded_file($file['tmp_name'], $path)) {
        $baseUrl = env('APP_URL', 'http://localhost/marketplace/backend');
        return "$baseUrl/uploads/$folder/$filename";
    }

    return null;
}
