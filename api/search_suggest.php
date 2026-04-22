<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

/**
 * Search Suggestion API (PostgreSQL Optimized)
 * Uses Trigram indexes to provide lightning-fast, fuzzy search suggestions.
 */

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    /**
     * Optimization: 
     * We use ILIKE for case-insensitive matching. 
     * The GIN trigram index we created makes this extremely fast.
     */
    $stmt = $pdo->prepare("
        SELECT DISTINCT word
        FROM (
            SELECT regexp_split_to_table(title, '\s+') as word
            FROM products
            WHERE status = 'approved' 
              AND title ILIKE ?
        ) s
        WHERE word ILIKE ?
        ORDER BY word ASC
        LIMIT 5
    ");

    // We match any title containing the query, but we prioritize 
    // words starting with the query (e.g. "iph" -> "iphone")
    $searchTerm = "%$q%";
    $wordStart  = "$q%"; 
    
    $stmt->execute([$searchTerm, $wordStart]);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Clean up results (lowercase and remove non-alphanumeric junk)
    $cleanResult = array_map(function($w) {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $w));
    }, $result);

    // Filter out duplicates and empty strings
    echo json_encode(array_values(array_unique(array_filter($cleanResult))));

} catch (Exception $e) {
    error_log("Search Suggestion Error: " . $e->getMessage());
    // Return empty array instead of crashing
    echo json_encode([]);
}
