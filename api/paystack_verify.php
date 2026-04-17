<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/ai_recommendations.php';

if(!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
check_csrf();

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
            // Use parameterized query with CASE statement instead of string interpolation
            $stmt = $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = CASE WHEN ? = '2_weeks' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) WHEN ? = 'weekly' THEN DATE_ADD(NOW(), INTERVAL 7 DAY) ELSE NULL END WHERE id = ?");
            $stmt->execute([$tier, $durStr, $durStr, $user_id]);

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'premium', ?, 'completed', ?, ?)");
            $stmt->execute([$user_id, $amount, $reference, ucfirst($tier) . " Upgrade"]);

            // Log successful payment
            logPaymentVerification($pdo, $user_id, $reference, 'success', null);

            $msg = "Upgrade successful! Welcome to " . ucfirst($tier);
        } else {
            // WALLET DEPOSIT
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $user_id]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'deposit', ?, 'completed', ?, 'Wallet Deposit (Paystack)')")->execute([$user_id, $amount, $reference]);

            // Log successful payment
            logPaymentVerification($pdo, $user_id, $reference, 'success', null);

            $msg = "₵" . number_format($amount, 2) . " deposited successfully!";
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
        error_log('paystack_verify.php DB error: ' . $error);

        // Log payment failure
        logPaymentVerification($pdo, $user_id, $reference, 'failed', $error);

        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
} else {
    $error = $response['message'] ?? 'Payment validation failed at Paystack';

    // Log payment failure
    logPaymentVerification($pdo, $_SESSION['user_id'], $reference, 'failed', $error);

    echo json_encode(['status' => 'error', 'message' => 'Payment validation failed at Paystack.']);
}
