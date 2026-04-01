<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/ai_recommendations.php';

if(!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? '';
$tier = $input['tier'] ?? ''; // Optional: for tier upgrades
$is_deposit = ($input['type'] ?? '') === 'deposit';

if(empty($reference)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Reference']);
    exit;
}

$secret_key = get_env_var('PAYSTACK_SECRET_KEY');
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $secret_key]);
$result = curl_exec($ch);
curl_close($ch);

if(!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Paystack Verification Failed']);
    exit;
}

$response = json_decode($result, true);

if($response['status'] && $response['data']['status'] === 'success') {
    $amount = $response['data']['amount'] / 100;
    $user_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();
        
        if($tier && in_array($tier, ['pro', 'premium'])) {
            // TIER UPGRADE
            $allTiers = getAccountTiers($pdo);
            $targetTier = $allTiers[$tier] ?? null;
            if(!$targetTier) throw new Exception("Target tier configuration missing.");

            $durStr = $targetTier['duration'];
            $expire_sql = "NULL";
            if($durStr === '2_weeks') $expire_sql = "DATE_ADD(NOW(), INTERVAL 14 DAY)";
            if($durStr === 'weekly') $expire_sql = "DATE_ADD(NOW(), INTERVAL 7 DAY)";

            $stmt = $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = $expire_sql WHERE id = ?");
            $stmt->execute([$tier, $user_id]);

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'premium', ?, 'completed', ?, ?)");
            $stmt->execute([$user_id, $amount, $reference, ucfirst($tier) . " Upgrade"]);
            
            $msg = "Upgrade successful! Welcome to " . ucfirst($tier);
        } else {
            // WALLET DEPOSIT
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $user_id]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'deposit', ?, 'completed', ?, 'Wallet Deposit (Paystack)')")->execute([$user_id, $amount, $reference]);
            $msg = "₵" . number_format($amount, 2) . " deposited successfully!";
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Payment validation failed at Paystack.']);
}
