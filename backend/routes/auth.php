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

        // SECURITY: Rate limiting
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimit = checkRateLimit($clientIp, 5, 900); // 5 attempts per 15 minutes
        if (!$rateLimit['allowed']) {
            logSecurityEvent($pdo, 'rate_limit_exceeded', "Login rate limit exceeded for IP $clientIp", null, $clientIp);
            jsonError('Too many login attempts. Try again in ' . ceil($rateLimit['cooldown'] / 60) . ' minutes.', 429);
        }

        $body = getJsonBody();
        $identifier = trim($body['email'] ?? $body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$identifier || !$password) {
            jsonError('Email/username and password are required');
        }

        // Support login via email OR username
        $stmt = $pdo->prepare("SELECT id, password, role, username, suspended, whatsapp_joined, terms_accepted FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([strtolower($identifier), $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            // Record failed attempt
            recordFailedLogin($clientIp);
            logSecurityEvent($pdo, 'failed_login', "Failed login attempt for identifier: $identifier from IP $clientIp", null, $clientIp);
            jsonError('Invalid credentials', 401);
        }

        $isSuspended = filter_var($user['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isSuspended) {
            logSecurityEvent($pdo, 'suspended_account_login', "Suspended account login attempt: " . $user['username'], $user['id'], $clientIp);
            jsonError('Your account has been suspended. Contact admin for assistance.', 403);
        }

        // Clear rate limit on successful login
        clearRateLimit($clientIp);
        logSecurityEvent($pdo, 'successful_login', "User logged in: " . $user['username'], $user['id'], $clientIp);

        ensureLegacySessionStarted();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

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
        $termsAccepted = !empty($body['terms']);

        // Validate basics
        if (!$username || !$email || !$password) {
            jsonError('Username, email, and password are required');
        }
        if (!validateEmail($email)) {
            jsonError('Invalid email address');
        }
        // SECURITY: Require 12+ character passwords with complexity
        if (strlen($password) < 12) {
            jsonError('Password must be at least 12 characters');
        }
        if (
            !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\\\|,.<>\/?]/', $password)
        ) {
            jsonError('Password must contain uppercase, lowercase, number, and special character');
        }
        if (!in_array($role, ['buyer', 'seller'])) {
            jsonError('Role must be buyer or seller');
        }
        if (!$termsAccepted) {
            jsonError('You must accept the Terms & Conditions');
        }
        if ($faculty === '') {
            jsonError('Faculty is required');
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

        if ($role === 'seller' && (!$department || !$level || !$phone)) {
            jsonError('Sellers must provide department, level, and phone');
        }

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

        $insertParams = [
            $username, $email, $hashedPassword, $role, $faculty,
            $department, $level, $hall, $phone, $profilePic, $myReferralCode, $referredBy
        ];

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, faculty, department, level, hall, phone, 
                                 profile_pic, referral_code, referred_by, seller_tier, terms_accepted, accepted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'basic', true, NOW())
                RETURNING id
            ");
            $stmt->execute($insertParams);
            $nextId = (int) $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, faculty, department, level, hall, phone, 
                                 profile_pic, referral_code, referred_by, seller_tier, terms_accepted, accepted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'basic', 1, NOW())
            ");
            $stmt->execute($insertParams);
            $nextId = (int) $pdo->lastInsertId();
        }

        if ($nextId <= 0) {
            jsonError('Registration failed. Please try again.', 500);
        }

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

        ensureLegacySessionStarted();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $nextId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

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
                'terms_accepted' => true,
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

        // Check for pending vacation requests
        $vacStmt = $pdo->prepare("SELECT id FROM vacation_requests WHERE seller_id = ? AND status = 'pending'");
        $vacStmt->execute([$user['id']]);
        $user['vacation_pending'] = (bool)$vacStmt->fetch();

        jsonResponse(['user' => $user]);
        break;

    // ── ACCEPT TERMS ──
    case 'session':
        if ($method !== 'POST') jsonError('Method not allowed', 405);

        $auth = authenticate();
        $user = getUser($pdo, $auth['user_id']);
        if (!$user) jsonError('User not found', 404);

        ensureLegacySessionStarted();

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        jsonResponse([
            'success' => true,
            'redirect_url' => $user['role'] === 'admin' ? '/admin/' : '/dashboard.php',
        ]);
        break;

    case 'accept-terms':
        if ($method !== 'POST') jsonError('Method not allowed', 405);

        $auth = authenticate();

        $boolT = sqlBool(true, $pdo);
        $pdo->prepare("UPDATE users SET terms_accepted = $boolT, accepted_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$auth['user_id']]);

        jsonSuccess('Terms accepted');
        break;

    default:
        jsonError('Auth endpoint not found', 404);
}
