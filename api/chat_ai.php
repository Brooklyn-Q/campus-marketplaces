<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../includes/db.php';
    require_once '../includes/ai_recommendations.php';
    check_csrf();

    $input = json_decode(file_get_contents('php://input'), true);
    $query = trim($input['message'] ?? '');

    if (empty($query)) {
        echo json_encode(['response' => "I didn't quite catch that. How can I assist you on the marketplace today?"]);
        exit;
    }

    // Context Gathering
    $user_info = null;
    $user_role = 'guest';
    $unread_count = 0;
    $user_products = [];
    $allTiers = getAccountTiers($pdo);

    if(isset($_SESSION['user_id'])) {
        $user_info = getUser($pdo, $_SESSION['user_id']);
        if($user_info) {
            $user_role = $user_info['role'];
            $unread_count = getUnreadCount($pdo, $user_info['id']);
            $stmt = $pdo->prepare("SELECT title, price FROM products WHERE user_id = ? LIMIT 5");
            $stmt->execute([$user_info['id']]);
            $user_products = $stmt->fetchAll();
        }
    }

    $stmt = $pdo->query("SELECT title, price, category FROM products WHERE status = 'approved' ORDER BY created_at DESC LIMIT 5");
    $recent_listings = $stmt->fetchAll();
    $allTiers = getAccountTiers($pdo);

    $system_context = [
        "platform" => "Campus Marketplace",
        "user" => ["role" => $user_role, "name" => $user_info['full_name'] ?? 'Guest', "unread" => $unread_count],
        "market" => ["recent" => $recent_listings, "tier_pricing" => $allTiers]
    ];

    $system_prompt = "You are the Senior Campus Marketplace AI. Context: " . json_encode($system_context);

    // AI Call
    $api_key = get_env_var('GEMINI_API_KEY');
    $response_text = "";

    if ($api_key && $api_key !== 'your_gemini_api_key_here') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($api_key);
        $payload = [
            "contents" => [["role" => "user", "parts" => [["text" => $system_prompt . "\n\nUser Question: " . $query]]]]
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300) {
            $data = json_decode($result, true);
            $response_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }
    }

    if (!$response_text) {
        // High Quality Fallback
        $q = strtolower($query);
        if(preg_match('/(sell|post|list)/i', $q)) $response_text = "To sell, click '+ Sell' in your dashboard. Ensure you have clear photos and a fair price!";
        elseif(preg_match('/(safe|scam)/i', $q)) $response_text = "Always meet in public campus areas and use Pay on Delivery for total safety.";
        else $response_text = "I'm the Campus Marketplace Assistant. I can help you find products, improve your listings, or stay safe. What's on your mind?";
    }

    echo json_encode(['response' => trim($response_text)]);
} catch (Throwable $e) {
    error_log('chat_ai.php error: ' . $e->getMessage());
    echo json_encode(['response' => "Connection stable, but a system error occurred. Please try again."]);
}
