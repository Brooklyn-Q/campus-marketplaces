<?php
// BUG FIX #10: Start output buffering immediately so any accidental whitespace
// or notice output before this point doesn't corrupt the JSON Content-Type header.
ob_start();

session_start();
header('Content-Type: application/json');

// Centralised JSON error exit — clears any buffered junk before responding.
function jsonError(string $msg, int $code = 200): never
{
    // Guard: only flush the buffer if one is actually open.
    // Prevents a notice if jsonError is ever called before ob_start()
    // or after the buffer has already been closed by a prior ob_end_clean().
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(['response' => $msg]);
    exit;
}

try {
    require_once '../includes/db.php';
    require_once '../includes/ai_recommendations.php';

    // BUG FIX #2: CSRF is valid here only for POST. This endpoint is POST-only,
    // so reject anything else before touching session or DB.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError("Method not allowed.", 405);
    }
    check_csrf();

    // BUG FIX #7: Validate json_decode returned an array, not null/scalar.
    // file_get_contents can return false; json_decode of invalid JSON returns null.
    $raw = file_get_contents('php://input');
    $input = ($raw !== false) ? json_decode($raw, true) : null;
    if (!is_array($input)) {
        jsonError("Invalid request payload.");
    }

    $query = trim($input['message'] ?? '');
    if (empty($query)) {
        jsonError("I didn't quite catch that. How can I assist you on the marketplace today?");
    }

    // BUG FIX #5: Simple per-session rate limit — max 20 AI requests per 60-second window.
    // Prevents endpoint abuse and runaway Gemini API costs.
    $_SESSION['ai_rate'] = $_SESSION['ai_rate'] ?? ['count' => 0, 'window_start' => time()];
    if (time() - $_SESSION['ai_rate']['window_start'] > 60) {
        $_SESSION['ai_rate'] = ['count' => 0, 'window_start' => time()];
    }
    $_SESSION['ai_rate']['count']++;
    if ($_SESSION['ai_rate']['count'] > 20) {
        jsonError("You're sending messages too quickly. Please wait a moment before trying again.");
    }

    // ── Context Gathering ─────────────────────────────────────────────────────
    $user_info = null;
    $user_role = 'guest';
    $unread_count = 0;
    $user_products = [];

    // BUG FIX #1: $allTiers was called twice (lines ~17 and ~30 in original).
    // Fetch once here and reuse below.
    $allTiers = getAccountTiers($pdo);

    if (isset($_SESSION['user_id'])) {
        $user_info = getUser($pdo, (int) $_SESSION['user_id']);
        if ($user_info) {
            $user_role = $user_info['role'];
            $unread_count = getUnreadCount($pdo, (int) $user_info['id']);
            $stmt = $pdo->prepare("SELECT title, price FROM products WHERE user_id = ? LIMIT 5");
            $stmt->execute([(int) $user_info['id']]);
            $user_products = $stmt->fetchAll();
        }
    }

    $stmt = $pdo->query(
        "SELECT title, price, category FROM products WHERE status = 'approved' ORDER BY created_at DESC LIMIT 5"
    );
    $recent_listings = $stmt->fetchAll();

    // BUG FIX #6: $user_info may be null (guest) or a row missing 'full_name'.
    // Guard both cases explicitly — don't rely on the ?? operator on a null array.
    $display_name = ($user_info && !empty($user_info['full_name']))
        ? $user_info['full_name']
        : 'Guest';

    // BUG FIX #9: Strip sensitive internal data (tier pricing, unread counts, product
    // inventory) from the context sent to the external Gemini API. Only send what the
    // AI genuinely needs to give a useful answer; treat the API call as a public boundary.
    $system_context = [
        "platform" => "Campus Marketplace",
        "user" => ["role" => $user_role, "name" => $display_name],
        "market" => ["recent_categories" => array_column($recent_listings, 'category')],
    ];
    $system_prompt =
        "You are a helpful Campus Marketplace AI assistant. " .
        "Be concise, friendly, and campus-focused. " .
        "Context: " . json_encode($system_context);

    // ── Gemini API Call ───────────────────────────────────────────────────────
    $api_key = get_env_var('GEMINI_API_KEY');
    $response_text = '';

    if ($api_key && $api_key !== 'your_gemini_api_key_here') {

        // BUG FIX #3 / #11: Never put the API key in the URL query string — it leaks
        // into server access logs, Referer headers, and curl verbose output.
        // Gemini supports the key as an x-goog-api-key request header instead.
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [["text" => $system_prompt . "\n\nUser: " . $query]]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                // BUG FIX #3: API key sent as a header, never in the URL.
                'x-goog-api-key: ' . $api_key,
            ],
            // BUG FIX #4a: Raised timeout from 5 s to 15 s — Gemini can take 8–12 s
            // under load; a 5 s timeout causes silent fallbacks that look like AI failures.
            CURLOPT_TIMEOUT => 15,
            // BUG FIX #4b: Enforce SSL peer verification (it's true by default on most
            // systems, but being explicit protects against misconfigured php.ini/curl).
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // BUG FIX #8: curl_exec returns false on network failure, not an empty string.
        // Must check for false before json_decode, or json_decode(false) returns null
        // silently and we'd never know a network error occurred.
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            error_log('chat_ai.php cURL error: ' . $curl_err);
        } elseif ($httpcode >= 200 && $httpcode < 300) {
            $data = json_decode($result, true);
            $response_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            error_log("chat_ai.php Gemini HTTP $httpcode: " . substr($result, 0, 200));
        }
    }

    // ── High-Quality Fallback ─────────────────────────────────────────────────
    if (empty(trim($response_text))) {
        $q = strtolower($query);
        if (preg_match('/(sell|post|list)/i', $q)) {
            $response_text = "To sell, click '+ Sell' in your dashboard. Use clear photos and a fair price for best results!";
        } elseif (preg_match('/(safe|scam|fraud)/i', $q)) {
            $response_text = "Always meet in public campus areas and use Pay on Delivery for total safety.";
        } elseif (preg_match('/(tier|pro|premium|upgrade)/i', $q)) {
            $response_text = "Upgrade your seller tier in your account settings to unlock more listings and priority placement.";
        } else {
            $response_text = "I'm the Campus Marketplace Assistant. I can help you find products, improve listings, or stay safe. What's on your mind?";
        }
    }

    ob_end_clean();
    echo json_encode(['response' => trim($response_text)]);

} catch (Throwable $e) {
    error_log('chat_ai.php error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode(['response' => "Connection stable, but a system error occurred. Please try again."]);
}