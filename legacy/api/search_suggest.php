<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT title FROM products WHERE status = 'approved'");
$stmt->execute();
$titles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$words = [];
foreach ($titles as $title) {
    $parts = preg_split('/\s+/', $title);
    foreach ($parts as $part) {
        $p = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $part)));
        if (strlen($p) > 2) {
            $words[$p] = ($words[$p] ?? 0) + 1;
        }
    }
}

$suggestions = [];
$q_lower = strtolower($q);

// Exact word match prefix
foreach ($words as $word => $count) {
    if (strpos($word, $q_lower) === 0) {
        $suggestions[] = ['word' => $word, 'score' => 100 + $count];
    } else {
        $lev = levenshtein($q_lower, $word);
        if ($lev <= 2 && strlen($word) > 3) {
            $suggestions[] = ['word' => $word, 'score' => 50 - $lev + $count];
        }
    }
}

usort($suggestions, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

$result = array_slice(array_unique(array_column($suggestions, 'word')), 0, 5);

echo json_encode($result);
