<?php
/**
 * AI Routes
 * POST /ai/chat — AI assistant
 * GET  /ai/describe?title=... — Generate product description
 */

// ── AI CHAT ASSISTANT ──
if ($method === 'POST' && $action === 'chat') {
    $body = getJsonBody();
    $question = trim($body['message'] ?? $body['question'] ?? '');

    if (!$question) jsonError('Message is required');

    $apiKey = env('GEMINI_API_KEY', '');

    if (!$apiKey) {
        jsonResponse(['response' => "I'm sorry, the AI assistant is currently unavailable. Please contact our admin through the messaging system for help."]);
    }

    $systemPrompt = "You are CampusBot, a helpful AI assistant for the Campus Marketplace — a peer-to-peer marketplace for university students. You help with:
- How to buy/sell products
- Account tiers (Basic, Pro, Premium)  
- Safety tips for in-person transactions
- How to use the messaging and order system
- Navigation guidance

Keep responses brief and friendly. If asked about something you don't know, suggest contacting the admin.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . urlencode($apiKey);
    $payload = [
        'contents' => [
            ['parts' => [['text' => $systemPrompt . "\n\nUser: " . $question]]]
        ],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 500],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';
        jsonResponse(['response' => $text]);
    }

    jsonResponse(['response' => "I'm having trouble connecting right now. Try again in a moment or contact admin."]);
}

// ── GENERATE PRODUCT DESCRIPTION ──
elseif ($method === 'GET' && $action === 'describe') {
    $title = getQueryParam('title', '');
    if (!$title) jsonError('Title is required');

    $apiKey = env('GEMINI_API_KEY', '');

    if ($apiKey) {
        $prompt = "Generate a short, compelling product listing description (2-3 sentences) for a campus marketplace item titled: \"$title\". Make it student-friendly and mention condition, value, and convenience.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . urlencode($apiKey);
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.8, 'maxOutputTokens' => 200],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text) {
            jsonResponse(['description' => trim($text)]);
        }
    }

    // Fallback
    jsonResponse([
        'description' => "Selling my slightly used $title. Great condition, carefully maintained with no hidden faults. Perfect for students looking for a reliable deal at an affordable price. Feel free to message me for negotiations!"
    ]);
}

else {
    jsonError('AI endpoint not found', 404);
}
