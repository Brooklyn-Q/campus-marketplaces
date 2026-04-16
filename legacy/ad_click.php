<?php
require_once 'includes/db.php';
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $pdo->prepare("UPDATE ad_placements SET clicks = clicks + 1 WHERE id = ?")->execute([$id]);
    } catch(Exception $e) {}
}
http_response_code(204);
exit;
