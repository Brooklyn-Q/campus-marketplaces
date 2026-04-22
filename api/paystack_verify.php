<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

/**
 * Paystack Payment Verification API (PostgreSQL Optimized)
 * Handles tier upgrades and wallet deposits via Paystack reference verification.
 */

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
check_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? '';
$tier = $input['tier'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($reference)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Reference']);
    exit;
}

// 1. Double Spending Prevention
$stmt = $pdo->prepare("SELECT id FROM transactions WHERE reference = ? LIMIT 1");
$stmt->execute([$reference]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Transaction already processed']);
    exit;
}

// 2. Paystack Verification
$secret_key = env('PAYSTACK_SECRET_KEY');
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $secret_key]);
$result = curl_exec($ch);
curl_close($ch);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Paystack Connection Failed']);
    exit;
}

$response = json_decode($result, true);

if ($response['status'] && ($response['data']['status'] ?? '') === 'success') {
    $amount = $response['data']['amount'] / 100;

    try {
        $pdo->beginTransaction();

        if ($tier && in_array($tier, ['pro', 'premium'])) {
            $allTiers = getAccountTiers($pdo);
            $targetTier = $allTiers[$tier] ?? null;
            if (!$targetTier)
                throw new Exception("Target tier configuration missing.");

            // FIX: Reliable PostgreSQL Interval calculation
            // We pass the number of days as an integer and multiply by a 1-day interval
            $days = ($targetTier['duration'] === '2_weeks') ? 14 : 7;

            $stmt = $pdo->prepare("
                UPDATE users 
                SET seller_tier = ?, 
                    tier_expires_at = NOW() + (? * INTERVAL '1 day') 
                WHERE id = ?
            ");
            $stmt->execute([$tier, $days, $user_id]);

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'premium', ?, 'completed', ?, ?)");
            $stmt->execute([$user_id, $amount, $reference, ucfirst($tier) . " Upgrade"]);

            logPaymentVerification($pdo, $user_id, $reference, 'success', null);
            $msg = "Upgrade successful! Welcome to " . ucfirst($tier);
        } else {
            // WALLET DEPOSIT
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $user_id]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'deposit', ?, 'completed', ?, 'Wallet Deposit')")->execute([$user_id, $amount, $reference]);

            logPaymentVerification($pdo, $user_id, $reference, 'success', null);
            $msg = "₵" . number_format($amount, 2) . " deposited successfully!";
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log('paystack_verify.php error: ' . $e->getMessage());
        logPaymentVerification($pdo, $user_id, $reference, 'failed', $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Processing error. Please contact support.']);
    }
} else {
    $error = $response['message'] ?? 'Payment validation failed';
    logPaymentVerification($pdo, $user_id, $reference, 'failed', $error);
    echo json_encode(['status' => 'error', 'message' => $error]);
}