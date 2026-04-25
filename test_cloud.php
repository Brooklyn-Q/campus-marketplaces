<?php
require 'includes/db.php';
require 'backend/config/cloudinary.php';

putenv('CLOUDINARY_CLOUD_NAME=dqfvibcdh');
putenv('CLOUDINARY_API_KEY=997977688773293');
putenv('CLOUDINARY_API_SECRET=XzpkdtN7OEsfdgDfPzxfNphuFEs');

echo "<pre>";
$realImage = __DIR__ . '/dummy.jpg'; // Assuming this exists
if (!file_exists($realImage)) {
    die("Need a real image to test. Looking for: " . $realImage);
}

$file = [
    'tmp_name' => $realImage,
    'type' => 'image/png',
    'name' => 'avatar.png',
    'error' => 0,
    'size' => filesize($realImage)
];

$url = uploadToCloudinary($file, 'test_folder');
echo "Result URL: " . var_export($url, true) . "\n";
echo "</pre>";
?>
