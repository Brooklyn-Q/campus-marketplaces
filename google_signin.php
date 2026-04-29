<?php
require_once 'includes/google_auth.php';

if (!googleSignInEnabled()) {
    setFlashMessage('auth_error', 'Google sign-in is not configured yet.');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$credential = trim($_POST['credential'] ?? '');
$mode = strtolower(trim($_POST['mode'] ?? 'login'));
$mode = in_array($mode, ['login', 'buyer', 'seller', 'admin'], true) ? $mode : 'login';

$redirectOnFailure = match ($mode) {
    'seller' => 'register.php?mode=seller',
    'buyer' => 'register.php?mode=buyer',
    'admin' => 'admin/settings.php',
    default => 'login.php',
};

if ($credential === '') {
    setFlashMessage('auth_error', 'Google sign-in did not return a credential.');
    redirect($redirectOnFailure);
}

$tokenInfo = fetchGoogleTokenInfo($credential);
$clientId = trim((string) env('GOOGLE_CLIENT_ID', ''));

if (!$tokenInfo || ($tokenInfo['aud'] ?? '') !== $clientId || empty($tokenInfo['email']) || ($tokenInfo['email_verified'] ?? 'false') !== 'true') {
    setFlashMessage('auth_error', 'Google sign-in could not be verified. Please try again.');
    redirect($redirectOnFailure);
}

$profile = normalizeGoogleProfile($tokenInfo);

if ($profile['email'] === '' || $profile['google_id'] === '') {
    setFlashMessage('auth_error', 'Google account information was incomplete.');
    redirect($redirectOnFailure);
}

$user = findGoogleLinkedUser($pdo, $profile);
$adminWhitelist = array_filter(array_map('trim', explode(',', (string) env('GOOGLE_ADMIN_EMAILS', ''))));
$adminWhitelisted = in_array($profile['email'], $adminWhitelist, true);

if ($mode === 'admin' && !$user && !$adminWhitelisted) {
    setFlashMessage('auth_error', 'This Google account is not allowed to create an admin session.');
    redirect('admin/settings.php');
}

if ($user) {
    if (filter_var($user['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        setFlashMessage('auth_error', 'Your account has been suspended. Contact admin for assistance.');
        redirect('login.php');
    }

    $user = linkGoogleIdentityToUser($pdo, $user, $profile);
    if (!$user) {
        setFlashMessage('auth_error', 'Google sign-in could not complete. Please try again.');
        redirect($redirectOnFailure);
    }

    startGoogleUserSession($pdo, $user, false);
}

if ($mode === 'login') {
    storePendingGoogleSignup($profile);
    setFlashMessage('auth_success', 'Google verified. Choose the type of account you want to create.');
    redirect('google_account_choice.php');
}

$role = $mode === 'admin' ? 'admin' : ($mode === 'seller' ? 'seller' : 'buyer');
$user = createGoogleMarketplaceUser($pdo, $profile, $role);

if (!$user) {
    setFlashMessage('auth_error', 'Google sign-in could not complete. Please try again.');
    redirect($redirectOnFailure);
}

startGoogleUserSession($pdo, $user, true);
