<?php
require_once __DIR__ . '/db.php';

// 2. Main Suggestion Entry
function get_smart_suggestions($pdo, $context_type, $context_data, $limit = 4, $premium_only = false) {

    // Ensure cache table exists (PostgreSQL version)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_recommendations_cache (
            id SERIAL PRIMARY KEY,
            context_hash VARCHAR(64) UNIQUE NOT NULL,
            recommended_product_ids VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL
        )");
    } catch(Exception $e) {}

    // Hash the context
    $hash_input = $context_type . json_encode($context_data) . $limit . ($premium_only ? 'premium' : 'all');
    $context_hash = hash('sha256', $hash_input);


    // Filter out IDs we shouldn't recommend (e.g. current product)
    $exclude_ids = get_exclude_ids($context_type, $context_data);

    // Check DB cache first
    $stmt = $pdo->prepare("SELECT recommended_product_ids FROM ai_recommendations_cache WHERE context_hash = ? AND expires_at > NOW()");
    $stmt->execute([$context_hash]);
    $cached = $stmt->fetchColumn();

    if ($cached !== false) {
        return fetch_products_by_ids($pdo, $cached, $exclude_ids, $limit);
    }


    // Call Gemini if API details are present
    $api_key = env('GEMINI_API_KEY');
    if ($api_key) {
        $prompt = build_ai_prompt($context_type, $context_data, $limit);
        $ai_keywords = call_gemini_api($api_key, $prompt);
        if ($ai_keywords && is_array($ai_keywords)) {
            $recommended_ids = perform_ai_keyword_search($pdo, $ai_keywords, $exclude_ids, $limit, $premium_only);
            if (count($recommended_ids) > 0) {

                // Cache it for 2 hours
                if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $stmt = $pdo->prepare("INSERT INTO ai_recommendations_cache (context_hash, recommended_product_ids, expires_at) VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '2 hours') ON CONFLICT (context_hash) DO UPDATE SET recommended_product_ids = EXCLUDED.recommended_product_ids, expires_at = EXCLUDED.expires_at");
                } else {
                    $stmt = $pdo->prepare("REPLACE INTO ai_recommendations_cache (context_hash, recommended_product_ids, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))");
                }
                $stmt->execute([$context_hash, implode(',', $recommended_ids)]);
                return fetch_products_by_ids($pdo, implode(',', $recommended_ids), $exclude_ids, $limit);
            }
        }
    }

    // Fallback if AI fails, no API key, or no results from AI keywords
    $fallback_ids = perform_fallback_search($pdo, $context_type, $context_data, $exclude_ids, $limit, $premium_only);
    if (!empty($fallback_ids)) {

        // Cache fallback results for 10 minutes to reduce DB load
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $stmt = $pdo->prepare("INSERT INTO ai_recommendations_cache (context_hash, recommended_product_ids, expires_at) VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '10 minutes') ON CONFLICT (context_hash) DO UPDATE SET recommended_product_ids = EXCLUDED.recommended_product_ids, expires_at = EXCLUDED.expires_at");
        } else {
            $stmt = $pdo->prepare("REPLACE INTO ai_recommendations_cache (context_hash, recommended_product_ids, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        }
        $stmt->execute([$context_hash, implode(',', $fallback_ids)]);
        return fetch_products_by_ids($pdo, implode(',', $fallback_ids), $exclude_ids, $limit);
    }

    return [];
}

// ── Internal Logistics ──

function get_exclude_ids($context_type, $context_data) {
    if ($context_type === 'product' && isset($context_data['id'])) {
        return [(int)$context_data['id']];
    }
    if ($context_type === 'cart' && is_array($context_data)) {
        return array_map('intval', array_column($context_data, 'id'));
    }
    return [];
}

function fetch_products_by_ids($pdo, $ids_csv, $exclude_ids, $limit) {
    if (empty($ids_csv)) return [];
    
    // Safety sanitization
    $ids = array_filter(array_map('intval', explode(',', $ids_csv)));
    // Filter excluded ones dynamically
    $ids = array_diff($ids, $exclude_ids);
    
    if (empty($ids)) return [];
    
    $in = implode(',', $ids);
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.seller_tier, u.verified,
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as main_image 
        FROM products p
        JOIN users u ON p.user_id = u.id
        WHERE p.id IN ($in) AND p.status='approved' LIMIT " . (int)$limit);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function build_ai_prompt($type, $data, $limit) {
    $base = "You are an AI recommendation engine for a campus marketplace. Return ONLY a JSON array of specific search keywords to finding matching products. Max 6 keywords. DO NOT wrap JSON in markdown block.\n\n";
    if ($type === 'product') {
        return $base . "The user is looking at a product titled: '{$data['title']}' in category '{$data['category']}'. Description: '{$data['description']}'. Suggest related keywords that would find accessories, alternative items, or complementary products.";
    }
    if ($type === 'home' && !empty($data)) {
        // e.g. recent views
        $titles = array_column($data, 'title');
        $titles_list = implode(', ', $titles);
        return $base . "The user recently viewed these products: {$titles_list}. Suggest keywords for items that this user would likely want to buy next.";
    }
    if ($type === 'cart' && !empty($data)) {
        $titles = array_column($data, 'name');
        $titles_list = implode(', ', $titles);
        return $base . "The user has these items in their cart: {$titles_list}. Suggest keywords for add-on accessories or relevant items to upsell before checkout.";
    }
    return $base . "Suggest trending campus items like electronics or dorm accessories.";
}

function call_gemini_api($api_key, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($api_key);
    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "responseMimeType" => "application/json"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Fail fast to avoid freezing website
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode >= 200 && $httpcode < 300) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = trim($text);
        // AI may return a JSON array
        $keywords = json_decode($text, true);
        if (is_array($keywords)) {
            return $keywords;
        }
    }
    return false;
}

function perform_ai_keyword_search($pdo, $keywords, $exclude_ids, $limit, $premium_only = false) {

    if (empty($keywords)) return [];
    
    $matches = [];
    $exclude_str = empty($exclude_ids) ? '0' : implode(',', array_map('intval', $exclude_ids));
    
    foreach ($keywords as $kw) {
        $kw = trim($kw);
        if (strlen($kw) < 3) continue;
        
        $term = "%$kw%";
        $p_sql = $premium_only ? " AND u.seller_tier = 'premium' " : "";
        $stmt = $pdo->prepare("SELECT p.id FROM products p JOIN users u ON p.user_id = u.id WHERE p.status='approved' AND p.id NOT IN ($exclude_str) $p_sql AND (p.title LIKE ? OR p.category LIKE ?) LIMIT " . (int)$limit);
        $stmt->execute([$term, $term]);

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $matches[$id] = ($matches[$id] ?? 0) + 1;
        }
    }
    
    // Sort by match frequency
    arsort($matches);
    $final_ids = array_slice(array_keys($matches), 0, $limit);
    return $final_ids;
}

function perform_fallback_search($pdo, $type, $data, $exclude_ids, $limit, $premium_only = false) {
    $exclude_str = empty($exclude_ids) ? '0' : implode(',', array_map('intval', $exclude_ids));
    
    $p_sql = $premium_only ? " AND u.seller_tier = 'premium' " : "";
    if ($type === 'product' && isset($data['category'])) {
        $stmt = $pdo->prepare("SELECT p.id FROM products p JOIN users u ON p.user_id = u.id WHERE p.category = ? AND p.id NOT IN ($exclude_str) AND p.status='approved' $p_sql ORDER BY p.views DESC LIMIT " . (int)$limit);
        $stmt->execute([$data['category']]);
        $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($res) >= $limit / 2) return $res;
    }
    
    // General fallback: Prioritize premium sellers, then most viewed
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $rand = $driver === 'pgsql' ? 'RANDOM()' : 'RAND()';
    $order = $driver === 'pgsql' 
        ? "CASE WHEN u.seller_tier = 'premium' THEN 1 ELSE 2 END ASC"
        : "(u.seller_tier = 'premium') DESC";
        
    $stmt = $pdo->prepare("SELECT p.id FROM products p JOIN users u ON p.user_id = u.id WHERE p.status='approved' AND p.id NOT IN ($exclude_str) $p_sql ORDER BY $order, p.views DESC, $rand LIMIT " . (int)$limit);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);

}
?>
