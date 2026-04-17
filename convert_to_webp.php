<?php
// PHP Script to convert JPG/JPEG images to WebP
$images = glob("IMG_*.JPG");
$images = array_merge($images, glob("IMG_*.JPEG"));
$images = array_map('strtolower', $images); // Ensure case-insensitive matches found
$images = array_unique(glob("IMG_*.[jJ][pP][gG]")); // Better glob

echo "=== Marketplace Media Optimizer ===\n";
echo "Found " . count($images) . " images to convert.\n\n";

foreach ($images as $img) {
    echo "Processing $img...\n";
    $info = getimagesize($img);
    if ($info && ($info['mime'] == 'image/jpeg' || $info['mime'] == 'image/jpg')) {
        $image = imagecreatefromjpeg($img);
        $output = preg_replace('/\.(jpg|jpeg)$/i', '.webp', $img);
        
        if (imagewebp($image, $output, 80)) {
            echo "Successfully created $output\n";
        } else {
            echo "ERROR: Failed to create $output (check GD library permissions)\n";
        }
        imagedestroy($image);
    } else {
        echo "Skipped $img (not a JPEG)\n";
    }
}
echo "\nConversion complete!\n";
?>
