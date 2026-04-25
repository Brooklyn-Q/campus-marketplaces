<?php
/**
 * Input Validation Helpers
 */

function validateRequired(array $data, array $fields): ?string {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            return "Field '$field' is required";
        }
    }
    return null;
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateMinLength(string $value, int $min): bool {
    return strlen(trim($value)) >= $min;
}

function validatePrice($price): bool {
    return is_numeric($price) && (float)$price > 0;
}

function validateInArray($value, array $allowed): bool {
    return in_array($value, $allowed, true);
}

function validateImageFile(array $file): ?string {
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return "Invalid image format. Allowed: " . implode(', ', $allowed);
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB
        return "File too large. Maximum 10MB.";
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "Upload error occurred.";
    }

    return null; // Valid
}

function validateMediaFile(array $file): ?string {
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'm4a', 'ogg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return "Invalid file format.";
    }

    if ($file['size'] > 50 * 1024 * 1024) { // 50MB for video
        return "File too large. Maximum 50MB.";
    }

    return null;
}

function getMediaType(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) return 'image';
    if (in_array($ext, ['mp4', 'webm', 'mov'])) return 'video';
    if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg'])) return 'audio';
    return 'text';
}

$VALID_CATEGORIES = [
    'Computer & Accessories',
    'Phone & Accessories',
    'Electrical Appliances',
    'Fashion',
    'Food & Groceries',
    'Education & Books',
    'Hostels for Rent'
];

$VALID_PROMO_TAGS = ['', '🔥 Hot Deal', '⚡ Flash Sale', '⏳ Limited Offer', '🎓 Student Special', '📦 Bundle Deal', '🏷️ Clearance'];

$ALLOWED_PROFILE_FIELDS = ['bio', 'department', 'level', 'hall', 'phone', 'faculty', 'whatsapp', 'instagram', 'linkedin', 'profile_pic'];
