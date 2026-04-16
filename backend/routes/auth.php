<?php
/**
 * Auth Routes
 * POST /auth/login      — Login and get JWT
 * POST /auth/register   — Register new user
 * GET  /auth/me         — Get current user profile
 * POST /auth/accept-terms — Accept terms & conditions
 */

switch ($action) {
    // ── LOGIN ──
    case 'login':
        if ($method !== 'POST') jsonError('Method not allowed', 405);

        $body = getJsonBody();
        $identifier = trim($body['email'] ?? $body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$identifier || !$password) {
            jsonError('Email/username and password are required');
        }

        // Support login via email OR username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([strtolower($identifier), $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonError('Invalid credentials', 401);
        }

        if ($user['suspended']) {
            jsonError('Your account has been suspended. Contact admin for assistance.', 403);
        }

        // Update last seen
        updateLastSeen($pdo, $user['id']);

        // Generate JWT
        $token = jwtEncode([
            'user_id' => $user['id'],
            'role' => $user['role'],
            'seller_tier' => $user['seller_tier'],
        ]);

        unset($user['password']);
        $user['has_unreviewed_orders'] = $user['role'] === 'buyer' ? hasUnreviewedOrders($pdo, $user['id']) : false;

        jsonResponse([
            'success' => true,
            'token' => $token,
            'user' => $user,
        ]);
        break;

    // ── REGISTER ──
    case 'register':
        if ($method !== 'POST') jsonError('Method not allowed', 405);

        // Support both JSON and multipart form data
        $body = !empty($_POST) ? $_POST : getJsonBody();

        $username = trim($body['username'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $role = $body['role'] ?? 'buyer';
        $faculty = trim($body['faculty'] ?? '');
        $referralCode = trim($body['referral_code'] ?? '');

        // Validate basics
        if (!$username || !$email || !$password) {
            jsonError('Username, email, and password are required');
        }
        if (!validateEmail($email)) {
            jsonError('Invalid email address');
        }
        if (!validateMinLength($password, 6)) {
            jsonError('Password must be at least 6 characters');
        }
        if (!in_array($role, ['buyer', 'seller'])) {
            jsonError('Role must be buyer or seller');
        }

        // Honeypot check
        if (!empty($body['website'])) {
            jsonError('Registration failed', 400);
        }

        // Check unique
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            jsonError('Username or email already taken');
        }

        // Seller extra fields
        $department = trim($body['department'] ?? '');
        $level = trim($body['level'] ?? '');
        $hall = trim($body['hall'] ?? $body['hall_residence'] ?? '');
        $phone = formatPhone(trim($body['phone'] ?? ''));

        // Handle referral
        $referredBy = null;
        if ($referralCode) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$referralCode]);
            $referrer = $stmt->fetch();
            if ($referrer) {
                $referredBy = $referrer['id'];
            }
        }

        // Handle profile pic upload
        $profilePic = null;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../config/cloudinary.php';
            $profilePic = uploadToCloudinary($_FILES['profile_pic'], 'marketplace/avatars');
        }

        // Generate referral code
        $myReferralCode = generateReferralCode();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, faculty, department, level, hall, phone, 
                             profile_pic, referral_code, referred_by, seller_tier) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'basic')
        ");
        $stmt->execute([
            $username, $email, $hashedPassword, $role, $faculty,
            $department, $level, $hall, $phone, $profilePic, $myReferralCode, $referredBy
        ]);
        
        $nextId = (int)$pdo->lastInsertId();

        // Process referral bonuses
        if ($referredBy) {
            $pdo->prepare("UPDATE users SET balance = balance + 5 WHERE id = ?")->execute([$referredBy]);
            $pdo->prepare("UPDATE users SET balance = balance + 2 WHERE id = ?")->execute([$nextId]);
            $pdo->prepare("INSERT INTO referrals (referrer_id, referred_user_id, bonus) VALUES (?, ?, 5)")
                ->execute([$referredBy, $nextId]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 5, 'completed', ?, 'Referral bonus')")
                ->execute([$referredBy, generateRef('REF')]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 2, 'completed', ?, 'Welcome bonus')")
                ->execute([$nextId, generateRef('REF')]);
        }

        // Generate JWT for immediate login
        $token = jwtEncode([
            'user_id' => $nextId,
            'role' => $role,
            'seller_tier' => 'basic',
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => [
                'id' => $nextId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'seller_tier' => 'basic',
                'profile_pic' => $profilePic,
                'terms_accepted' => 0,
                'referral_code' => $myReferralCode,
            ]
        ], 201);
        break;

    // ── GET CURRENT USER ──
    case 'me':
        if ($method !== 'GET') jsonError('Method not allowed', 405);

        $auth = authenticate();
        $user = getUser($pdo, $auth['user_id']);
        if (!$user) jsonError('User not found', 404);

        updateLastSeen($pdo, $user['id']);

        $user['has_unreviewed_orders'] = $user['role'] === 'buyer' ? hasUnreviewedOrders($pdo, $user['id']) : false;
        $user['unread_messages'] = getUnreadMessageCount($pdo, $user['id']);
        $user['unread_notifications'] = getUnreadNotificationCount($pdo, $user['id']);
        $user['wallet_balance'] = (float) $user['balance'];
        $user['badge'] = getBadgeData($user['seller_tier'] ?: 'basic');

        // Get tier info
        $tiers = getAccountTiers($pdo);
        $user['tier_info'] = $tiers[$user['seller_tier'] ?: 'basic'] ?? null;

        jsonResponse(['user' => $user]);
        break;

    // ── ACCEPT TERMS ──
    case 'accept-terms':
        if ($method !== 'POST') jsonError('Method not allowed', 405);

        $auth = authenticate();

        $pdo->prepare("UPDATE users SET terms_accepted = 1, accepted_at = NOW() WHERE id = ?")
            ->execute([$auth['user_id']]);

        jsonSuccess('Terms accepted');
        break;

    default:
        jsonError('Auth endpoint not found', 404);
}
