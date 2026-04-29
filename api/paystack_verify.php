<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

ensureFeatureSupportSchema($pdo);

function respond_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function normalize_tier_name($tier): string
{
    return strtolower(trim((string)$tier));
}

function parse_tier_duration(string $duration): array
{
    $value = strtolower(trim($duration));

    if ($value === '' || $value === '0' || $value === 'forever' || $value === 'lifetime') {
        return ['modify' => null, 'label' => 'lifetime access'];
    }

    if ($value === 'weekly' || $value === '1_week') {
        return ['modify' => '+1 week', 'label' => '1 week'];
    }

    if ($value === '2_weeks') {
        return ['modify' => '+2 weeks', 'label' => '2 weeks'];
    }

    if (preg_match('/^(\d+)_weeks?$/', $value, $matches)) {
        $weeks = max(1, (int)$matches[1]);
        return ['modify' => '+' . $weeks . ' weeks', 'label' => $weeks . ' week' . ($weeks === 1 ? '' : 's')];
    }

    if (preg_match('/^(\d+)_months?$/', $value, $matches)) {
        $months = max(1, (int)$matches[1]);
        return ['modify' => '+' . $months . ' months', 'label' => $months . ' month' . ($months === 1 ? '' : 's')];
    }

    if (is_numeric($value)) {
        $months = max(1, (int)$value);
        return ['modify' => '+' . $months . ' months', 'label' => $months . ' month' . ($months === 1 ? '' : 's')];
    }

    return ['modify' => '+1 month', 'label' => '1 month'];
}

function next_tier_expiry(?string $currentExpiry, string $duration): ?string
{
    $parsed = parse_tier_duration($duration);
    if ($parsed['modify'] === null) {
        return null;
    }

    $base = new DateTimeImmutable('now');
    if (!empty($currentExpiry)) {
        try {
            $existing = new DateTimeImmutable($currentExpiry);
            if ($existing > $base) {
                $base = $existing;
            }
        } catch (Exception $e) {
        }
    }

    return $base->modify($parsed['modify'])->format('Y-m-d H:i:s');
}

if (!isLoggedIn()) {
    respond_json(['status' => 'error', 'message' => 'Unauthorized']);
}

check_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$reference = trim((string)($input['reference'] ?? ''));
$requestedTier = normalize_tier_name($input['tier'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($reference === '') {
    respond_json(['status' => 'error', 'message' => 'Invalid reference']);
}

$stmt = $pdo->prepare("SELECT id FROM transactions WHERE reference = ? LIMIT 1");
$stmt->execute([$reference]);
if ($stmt->fetch()) {
    respond_json(['status' => 'error', 'message' => 'Transaction already processed']);
}

$secretKey = env('PAYSTACK_SECRET_KEY');
$url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretKey]);
$result = curl_exec($ch);
curl_close($ch);

if (!$result) {
    respond_json(['status' => 'error', 'message' => 'Paystack connection failed']);
}

$response = json_decode($result, true);
$paymentData = $response['data'] ?? [];

if (!($response['status'] ?? false) || ($paymentData['status'] ?? '') !== 'success') {
    $error = $response['message'] ?? 'Payment validation failed';
    logPaymentVerification($pdo, $userId, $reference, 'failed', $error);
    respond_json(['status' => 'error', 'message' => $error]);
}

$amount = round(((float)($paymentData['amount'] ?? 0)) / 100, 2);
$verifiedTier = normalize_tier_name($paymentData['metadata']['tier'] ?? '');
$tier = $verifiedTier !== '' ? $verifiedTier : $requestedTier;

try {
    $pdo->beginTransaction();

    if ($tier !== '') {
        $allTiers = getAccountTiers($pdo);
        $targetTier = $allTiers[$tier] ?? null;
        if (!$targetTier) {
            throw new Exception('Target tier configuration missing.');
        }

        $expectedAmount = round((float)($targetTier['price'] ?? 0), 2);
        if ($expectedAmount <= 0) {
            throw new Exception('Selected tier is not payable.');
        }

        if (abs($amount - $expectedAmount) > 0.01) {
            throw new Exception('Verified amount does not match the selected tier.');
        }

        $currentTierStmt = $pdo->prepare("SELECT seller_tier, tier_expires_at FROM users WHERE id = ? LIMIT 1");
        $currentTierStmt->execute([$userId]);
        $currentTierData = $currentTierStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $nextExpiry = next_tier_expiry($currentTierData['tier_expires_at'] ?? null, (string)($targetTier['duration'] ?? ''));
        $durationLabel = parse_tier_duration((string)($targetTier['duration'] ?? ''))['label'];

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET seller_tier = ?,
                tier_expires_at = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$tier, $nextExpiry, $userId]);

        $description = ucfirst($tier) . ' Upgrade';
        if ($durationLabel !== '') {
            $description .= ' (' . $durationLabel . ')';
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, status, reference, description)
            VALUES (?, ?, ?, 'completed', ?, ?)
        ");
        $insertStmt->execute([$userId, $tier, $amount, $reference, $description]);

        $pdo->commit();
        logPaymentVerification($pdo, $userId, $reference, 'success', null);
        respond_json([
            'status' => 'success',
            'message' => 'Upgrade successful! Your ' . ucfirst($tier) . ' plan is now active.',
        ]);
    }

    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
    $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status, reference, description)
        VALUES (?, 'deposit', ?, 'completed', ?, 'Wallet Deposit')
    ")->execute([$userId, $amount, $reference]);

    $pdo->commit();
    logPaymentVerification($pdo, $userId, $reference, 'success', null);
    respond_json([
        'status' => 'success',
        'message' => 'GHS ' . number_format($amount, 2) . ' deposited successfully!',
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('paystack_verify.php error: ' . $e->getMessage());
    logPaymentVerification($pdo, $userId, $reference, 'failed', $e->getMessage());
    respond_json(['status' => 'error', 'message' => 'Processing error. Please contact support.']);
}
