<?php
/**
 * Payment Routes (Paystack)
 * POST /payments/initialize — Start payment for tier upgrade
 * GET  /payments/verify/:reference — Verify payment
 * POST /payments/deposit — Wallet deposit
 */

$auth = authenticate();

// ── INITIALIZE PAYMENT ──
if ($method === 'POST' && $action === 'initialize') {
    $body = getJsonBody();
    $type = $body['type'] ?? 'premium'; // 'pro', 'premium', 'deposit'
    $amount = 0;
    $description = '';

    if ($type === 'deposit') {
        $amount = (float) ($body['amount'] ?? 0);
        if ($amount < 1) jsonError('Minimum deposit is ₵1');
        $description = 'Wallet deposit';
    } else {
        $tiers = getAccountTiers($pdo);
        $tier = $tiers[$type] ?? null;
        if (!$tier) jsonError('Invalid tier');
        $amount = (float) $tier['price'];
        $description = ucfirst($type) . ' tier upgrade';
    }

    $reference = generateRef('PAY');
    $user = getUser($pdo, $auth['user_id']);

    // Record pending transaction
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, ?, ?, 'pending', ?, ?)")
        ->execute([$auth['user_id'], $type === 'deposit' ? 'deposit' : 'premium', $amount, $reference, $description]);

    // Initialize with Paystack
    $paystackKey = env('PAYSTACK_SECRET_KEY');
    if ($paystackKey) {
        $payload = [
            'email' => $user['email'],
            'amount' => (int)($amount * 100), // kobo/pesewas
            'reference' => $reference,
            'currency' => 'GHS',
            'callback_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/dashboard?payment=verify&reference=' . $reference,
            'metadata' => [
                'user_id' => $auth['user_id'],
                'type' => $type,
            ],
        ];

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $paystackKey,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response && ($response['status'] ?? false)) {
            jsonResponse([
                'success' => true,
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $reference,
            ]);
        }
    }

    // Fallback: return reference for client-side Paystack popup
    jsonResponse([
        'success' => true,
        'reference' => $reference,
        'amount' => $amount,
        'email' => $user['email'],
    ]);
}

// ── VERIFY PAYMENT ──
elseif ($method === 'GET' && $action === 'verify') {
    $reference = $param ?: getQueryParam('reference', '');
    if (!$reference) jsonError('Reference is required');

    // Check transaction exists
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$reference]);
    $tx = $stmt->fetch();
    if (!$tx) jsonError('Transaction not found', 404);
    if ($tx['status'] === 'completed') jsonSuccess('Already verified', ['transaction' => $tx]);

    // Verify with Paystack
    $paystackKey = env('PAYSTACK_SECRET_KEY');
    $verified = false;

    if ($paystackKey) {
        $ch = curl_init("https://api.paystack.co/transaction/verify/$reference");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $paystackKey]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $verified = ($response['data']['status'] ?? '') === 'success';
    }

    if (!$verified) jsonError('Payment verification failed');

    $pdo->beginTransaction();
    try {
        // Mark completed
        $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE reference = ?")->execute([$reference]);

        if ($tx['type'] === 'premium' || $tx['type'] === 'pro') {
            // Determine tier from description
            $tier = str_contains($tx['description'], 'Pro') ? 'pro' : 'premium';
            $tiers = getAccountTiers($pdo);
            $tierInfo = $tiers[$tier] ?? null;

            $expiresAt = null;
            if ($tierInfo && $tierInfo['duration'] !== 'forever') {
                $days = $tierInfo['duration'] === 'weekly' ? 7 : 14;
                $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
            }

            $pdo->prepare("UPDATE users SET seller_tier = ?, tier_expires_at = ? WHERE id = ?")
                ->execute([$tier, $expiresAt, $tx['user_id']]);

            auditLog($pdo, $tx['user_id'], "Upgraded to $tier tier via payment $reference", 'payment', $tx['id']);
        } elseif ($tx['type'] === 'deposit') {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                ->execute([$tx['amount'], $tx['user_id']]);
        }

        $pdo->commit();
        jsonSuccess('Payment verified and applied');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonError('Failed to apply payment', 500);
    }
}

else {
    jsonError('Payment endpoint not found', 404);
}
