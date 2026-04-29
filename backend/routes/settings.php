<?php
/**
 * Settings / Public Configs Route
 * GET /settings/tiers — Fetch account tiers
 */

if ($method === 'GET' && $action === 'tiers') {
    $stmt = $pdo->query("
        SELECT * FROM account_tiers
        ORDER BY CASE tier_name
            WHEN 'basic' THEN 1
            WHEN 'pro' THEN 2
            WHEN 'premium' THEN 3
            ELSE 99
        END, price ASC
    ");
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tiers as &$tier) {
        if (isset($tier['benefits']) && is_string($tier['benefits'])) {
            $tier['benefits'] = json_decode($tier['benefits'], true) ?: [];
        }
    }
    jsonResponse(['success' => true, 'tiers' => $tiers]);
} else {
    jsonError('Settings endpoint not found', 404);
}
