<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$apiKey = env('GEMINI_API_KEY', '');
echo "API Key loaded: " . ($apiKey ? "YES" : "NO") . "\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . urlencode($apiKey);
$payload = [
    'contents' => [['parts' => [['text' => "Say hello world"]]]],
    'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 200],
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
echo "cURL Error: " . curl_error($ch) . "\n";
echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "Response: " . $response . "\n";

?>
