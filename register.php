<?php
require_once 'includes/db.php';
if (isLoggedIn())
    redirect(isAdmin() ? 'admin/' : 'dashboard.php');

$error = getFlashMessage('auth_error');
$success = getFlashMessage('auth_success');
$googleEnabled = googleSignInEnabled();
$mode = $_GET['mode'] ?? 'buyer'; // buyer or seller

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $mode = $_POST['mode'] ?? 'buyer';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ref_code = trim($_POST['referral_code'] ?? '');
    $honeypot = $_POST['website'] ?? ''; // anti-bot
    $terms = $_POST['terms'] ?? '';

    // Faculty (required for all users)
    $faculty = trim($_POST['faculty'] ?? '');

    // Seller-specific
    $department = trim($_POST['department'] ?? '');
    $level = $_POST['level'] ?? '';
    // Use hall_residence from POST for both hall and hall_residence to maintain backwards compatibility
    $hall = trim($_POST['hall_residence'] ?? '');
    $hall_residence = trim($_POST['hall_residence'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($honeypot)) {
        $error = "Bot detected.";
    } elseif (empty($terms)) {
        $error = "You must accept the Terms & Conditions.";
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
        $error = "Password must be at least 12 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (empty($faculty)) {
        $error = "Please select your faculty.";
    } elseif ($mode === 'seller' && (empty($department) || empty($level) || empty($phone))) {
        $error = "Sellers must fill department, level, and phone.";
    } else {
        $email = strtolower($email); // Always store email in lowercase
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = "Email or Username already taken.";
        } else {
            try {
                $pdo->beginTransaction();

                $referred_by = null;
                if (!empty($ref_code)) {
                    $ref_stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $ref_stmt->execute([$ref_code]);
                    $r = $ref_stmt->fetch();
                    if ($r)
                        $referred_by = $r['id'];
                }

                // Handle profile pic upload for sellers
                $pic = null;
                if ($mode === 'seller' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        // SECURITY: Validate MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                        // SECURITY: Check file size (10MB max for avatars)
                        $maxFileSize = 10 * 1024 * 1024;
                        if (in_array($mimeType, $allowedMimes) && $_FILES['profile_pic']['size'] <= $maxFileSize) {
                            if (!is_dir('uploads/avatars'))
                                mkdir('uploads/avatars', 0755, true);

                            // SECURITY: Strip EXIF and re-encode
                            $image = null;
                            if ($mimeType === 'image/jpeg') {
                                $image = @imagecreatefromjpeg($_FILES['profile_pic']['tmp_name']);
                            } elseif ($mimeType === 'image/png') {
                                $image = @imagecreatefrompng($_FILES['profile_pic']['tmp_name']);
                            } elseif ($mimeType === 'image/webp') {
                                $image = @imagecreatefromwebp($_FILES['profile_pic']['tmp_name']);
                            }

                            if ($image) {
                                $pic = 'avatars/' . uniqid('av_', true) . '.' . $ext;
                                $uploadPath = 'uploads/' . $pic;
                                // Ensure path doesn't escape uploads directory
                                $realPath = realpath(dirname($uploadPath));
                                if ($realPath && strpos($realPath, realpath('uploads')) === 0) {
                                    if ($ext === 'jpg' || $ext === 'jpeg') {
                                        imagejpeg($image, $uploadPath, 85);
                                    } elseif ($ext === 'png') {
                                        imagepng($image, $uploadPath, 8);
                                    } elseif ($ext === 'webp') {
                                        imagewebp($image, $uploadPath, 85);
                                    }
                                }
                            }
                        }
                    }
                }

                // Format phone
                if ($phone && substr($phone, 0, 1) === '0') {
                    $phone = '+233' . substr($phone, 1);
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $new_ref = generateReferralCode();
                $role = ($mode === 'seller') ? 'seller' : 'buyer';

                $insertParams = [$username, $email, $hashed, $role, $faculty, $department ?: null, $level ?: null, $hall ?: null, $hall_residence ?: null, $phone ?: null, $pic, $new_ref, $referred_by];
                if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, department, level, hall, hall_residence, phone, profile_pic, referral_code, referred_by, terms_accepted, accepted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,true,NOW()) RETURNING id");
                    $stmt->execute($insertParams);
                    $user_id = (int) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, faculty, department, level, hall, hall_residence, phone, profile_pic, referral_code, referred_by, terms_accepted, accepted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
                    $stmt->execute($insertParams);
                    $user_id = (int) $pdo->lastInsertId();
                }

                if ($user_id <= 0) {
                    throw new RuntimeException('Could not determine new user ID after registration.');
                }

                // Referral bonuses
                if ($referred_by) {
                    $pdo->prepare("UPDATE users SET balance = balance + 5.00 WHERE id = ?")->execute([$referred_by]);
                    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 5.00, 'completed', ?, 'Referral bonus')")->execute([$referred_by, generateRef('REF')]);
                    $pdo->prepare("UPDATE users SET balance = balance + 2.00 WHERE id = ?")->execute([$user_id]);
                    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, description) VALUES (?, 'referral', 2.00, 'completed', ?, 'Signup referral bonus')")->execute([$user_id, generateRef('REF')]);
                    $pdo->prepare("INSERT INTO referrals (referrer_id, referred_user_id, bonus) VALUES (?,?,5.00)")->execute([$referred_by, $user_id]);
                }

                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                redirect($role === 'admin' ? 'admin/' : 'dashboard.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('register.php registration failed: ' . $e->getMessage());
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .google-auth-button-wrap {
        display: flex;
        justify-content: center;
        width: 100%;
        min-height: 44px;
    }

    @media (max-width: 640px) {
        .auth-wrapper {
            padding: 14px !important;
            align-items: flex-start !important;
        }

        .register-card {
            padding: 1.1rem !important;
        }

        .register-card h1 {
            font-size: 1.55rem !important;
        }
    }
</style>

<div class="auth-wrapper"
    style="min-height: calc(100vh - 100px); display:flex; align-items:center; justify-content:center; padding: 20px;">
    <div class="glass form-container fade-in register-card"
        style="width:100%; max-width:680px; box-shadow:0 32px 80px rgba(0,0,0,0.12); border-radius:32px;">

        <div class="text-center" style="margin-bottom:2rem;">
            <div
                style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:22px; background:linear-gradient(135deg, rgba(0,113,227,0.12), rgba(0,113,227,0.06)); margin-bottom:1.25rem; border:1px solid rgba(0,113,227,0.1);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0071e3" stroke-width="2.2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <line x1="19" y1="8" x2="19" y2="14" />
                    <line x1="22" y1="11" x2="16" y2="11" />
                </svg>
            </div>
            <h1 style="font-size:2rem; font-weight:800; letter-spacing:-0.03em; margin:0;">Create Account</h1>
            <p style="color:var(--text-muted); font-size:1.05rem; margin-top:0.4rem; font-weight:500;">Join your
                university marketplace today</p>
        </div>

        <!-- Mode Tabs -->
        <div
            style="display:flex; gap:0.5rem; margin-bottom:2.5rem; background:rgba(0,0,0,0.04); padding:6px; border-radius:18px; border:1px solid rgba(0,0,0,0.04);">
            <a href="?mode=buyer"
                style="flex:1; border-radius:14px; padding:0.75rem; text-align:center; font-weight:700; font-size:0.9rem; transition:all 0.25s cubic-bezier(0.2, 0, 0, 1); text-decoration:none; <?= $mode === 'buyer' ? 'background:#fff; color:#0071e3; box-shadow:0 4px 12px rgba(0,0,0,0.1);' : 'color:var(--text-muted);' ?>">
                🛒 Buyer
            </a>
            <a href="?mode=seller"
                style="flex:1; border-radius:14px; padding:0.75rem; text-align:center; font-weight:700; font-size:0.9rem; transition:all 0.25s cubic-bezier(0.2, 0, 0, 1); text-decoration:none; <?= $mode === 'seller' ? 'background:#fff; color:#0071e3; box-shadow:0 4px 12px rgba(0,0,0,0.1);' : 'color:var(--text-muted);' ?>">
                🏪 Seller
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error fade-in" style="text-align:center; margin-bottom:2rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="vertical-align:middle;margin-right:4px;">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success fade-in" style="text-align:center; margin-bottom:2rem;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($googleEnabled): ?>
            <div style="margin-bottom:1.8rem;">
                <div id="googleRegisterButton" class="google-auth-button-wrap"></div>
                <p style="font-size:0.82rem; color:var(--text-muted); text-align:center; margin-top:0.85rem;">
                    Continue with Google as a <?= htmlspecialchars($mode) ?>. You can finish profile details after sign-up.
                </p>
                <form id="googleRegisterForm" method="POST" action="google_signin.php" style="display:none;">
                    <input type="hidden" name="credential" id="googleRegisterCredential">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                </form>
                <script src="https://accounts.google.com/gsi/client" async defer></script>
                <script>
                    function handleGoogleRegister(response) {
                        const input = document.getElementById('googleRegisterCredential');
                        if (!response || !response.credential || !input) return;
                        input.value = response.credential;
                        document.getElementById('googleRegisterForm').submit();
                    }
                    window.addEventListener('load', function () {
                        if (!window.google || !google.accounts || !document.getElementById('googleRegisterButton')) return;
                        const buttonWidth = Math.min(420, Math.max(220, document.getElementById('googleRegisterButton').offsetWidth || 0));
                        google.accounts.id.initialize({
                            client_id: <?= json_encode(env('GOOGLE_CLIENT_ID', '')) ?>,
                            callback: handleGoogleRegister
                        });
                        google.accounts.id.renderButton(
                            document.getElementById('googleRegisterButton'),
                            { theme: 'outline', size: 'large', shape: 'pill', text: 'signup_with', width: buttonWidth }
                        );
                    });
                </script>
            </div>
            <div style="display:flex; align-items:center; gap:12px; margin:1.5rem 0 2rem;">
                <div style="height:1px; background:rgba(0,0,0,0.08); flex:1;"></div>
                <span style="font-size:0.82rem; color:var(--text-muted); font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">or use email</span>
                <div style="height:1px; background:rgba(0,0,0,0.08); flex:1;"></div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="registerForm">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="<?= $mode ?>">
            <div style="display:none;"><input type="text" name="website" tabindex="-1"></div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" id="regUsername" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" id="regEmail" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" id="regPassword" required minlength="12"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:&quot;\\|,.<>\/?]).{12,}"
                        title="Must contain at least 12 characters, including uppercase, lowercase, number, and special character.">
                </div>
                <div class="form-group">
                    <label>Referral Code (Optional)</label>
                    <input type="text" name="referral_code" class="form-control" placeholder="Code">
                </div>
            </div>

            <div class="form-group" style="position:relative;">
                <label>Select Your Faculty * <span
                        style="font-size:0.72rem; color:var(--text-muted); font-weight:400;">Type to
                        search</span></label>
                <input type="text" name="faculty" id="facultyInput" class="form-control" list="facultyList" required
                    autocomplete="off" placeholder="Start typing faculty name...">
                <datalist id="facultyList">
                    <option value="Faculty of Applied Arts and Technology">
                    <option value="Faculty of Applied Sciences">
                    <option value="Faculty of Engineering">
                    <option value="Faculty of Business Studies">
                    <option value="Faculty of Built and Natural Environment">
                    <option value="Faculty of Health and Allied Sciences">
                    <option value="Faculty of Maritime and Nautical Studies">
                    <option value="Faculty of Media Technology and Liberal Studies">
                </datalist>
            </div>

            <?php if ($mode === 'seller'): ?>
                <div class="form-row">
                    <div class="form-group" style="position:relative;">
                        <label>Department * <span style="font-size:0.72rem; color:var(--text-muted); font-weight:400;">Type
                                to search</span></label>
                        <input type="text" name="department" id="departmentInput" class="form-control" list="departmentList"
                            required autocomplete="off" placeholder="Select faculty first...">
                        <datalist id="departmentList"></datalist>
                    </div>
                    <div class="form-group">
                        <label>Academic Level *</label>
                        <select name="level" class="form-control" required>
                            <option value="">Select Level</option>
                            <option value="100">Level 100</option>
                            <option value="200">Level 200</option>
                            <option value="300">Level 300</option>
                            <option value="400">Level 400</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="position:relative;">
                        <label>Hall / Residence <span
                                style="font-size:0.72rem; color:var(--text-muted); font-weight:400;">Type to
                                search</span></label>
                        <input type="text" name="hall_residence" id="hallInput" class="form-control" list="hallList"
                            autocomplete="off" placeholder="Start typing residence...">
                        <datalist id="hallList">
                            <option value="Ahanta Hall">
                            <option value="Nzema-Mensah Hall">
                            <option value="Prof Duncan Hall">
                            <option value="University Hall">
                            <option value="Akatakyi Campus Hostel">
                            <option value="BU Campus Accommodation">
                            <option value="Off Campus">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="024XXXXXXX"
                            pattern="(0[0-9]{9}|\+233[0-9]{9})">
                    </div>
                </div>
                <div class="form-group">
                    <label>Profile Photo <span
                            style="font-weight:400; font-size:0.8rem; color:var(--text-muted);">(Optional)</span></label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/*" style="padding:0.6rem;">
                </div>
            <?php endif; ?>

            <div class="form-group"
                style="display:flex; gap:0.75rem; align-items:flex-start; margin:2rem 0; background:rgba(0,113,227,0.03); padding:1.25rem; border-radius:20px; border:1px solid rgba(0,113,227,0.08);">
                <input type="checkbox" name="terms" value="1" id="termsCheckbox" required
                    style="width:20px; height:20px; margin-top:3px; cursor:pointer;">
                <label for="termsCheckbox"
                    style="font-size:0.9rem; color:var(--text-main); line-height:1.5; font-weight:500; margin:0;">
                    I have read and agree to the <a href="javascript:void(0)" onclick="openTermsModal()"
                        style="color:var(--primary); font-weight:800; text-decoration:underline;">Terms &
                        Conditions</a>. (Please read first)
                </label>
            </div>

            <button type="submit" id="regSubmitBtn" class="btn btn-primary"
                style="width:100%; justify-content:center; padding:1.1rem; font-size:1.1rem; font-weight:700;">Create
                Account</button>
        </form>

        <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid rgba(0,0,0,0.06); text-align:center;">
            <p style="font-size:0.95rem; color:var(--text-muted); margin:0;">
                Already have an account? <a href="login.php" style="color:var(--primary); font-weight:700;">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    const facultyDepartmentsMap = {
        "Faculty of Applied Arts and Technology": [
            "Ceramics Technology",
            "Fashion Design and Technology",
            "Graphic Design Technology",
            "Industrial Painting and Design",
            "Sculpture Technology",
            "Textiles Design and Technology"
        ],
        "Faculty of Applied Sciences": [
            "Computer Science",
            "Hospitality Management",
            "Mathematics, Statistics, and Actuarial Science",
            "Tourism Management",
            "Industrial and Health Science"
        ],
        "Faculty of Engineering": [
            "Civil Engineering",
            "Electrical/Electronic Engineering",
            "Mechanical Engineering (Automotive, Plant, Production, Refrigeration)",
            "Oil and Natural Gas Engineering",
            "Renewable Energy Engineering"
        ],
        "Faculty of Business Studies": [
            "Accounting and Finance",
            "Marketing and Strategy",
            "Procurement and Supply Chain Management",
            "Secretaryship and Management Studies",
            "Professional Studies"
        ],
        "Faculty of Built and Natural Environment": [
            "Building Technology",
            "Estate Management",
            "Interior Design and Upholstery Technology"
        ],
        "Faculty of Health and Allied Sciences": [
            "Medical Laboratory Sciences",
            "Pharmaceutical Sciences"
        ],
        "Faculty of Maritime and Nautical Studies": [
            "Marine Engineering",
            "Maritime Transport"
        ],
        "Faculty of Media Technology and Liberal Studies": [
            "Media and Communication Technology"
        ]
    };

    document.addEventListener('DOMContentLoaded', function () {
        const facultyInput = document.getElementById('facultyInput');
        const departmentInput = document.getElementById('departmentInput');
        const departmentList = document.getElementById('departmentList');

        if (facultyInput) {
            const updateDepartments = function () {
                if (!departmentInput || !departmentList) return;
                const selectedFaculty = facultyInput.value;
                departmentList.innerHTML = '';
                departmentInput.value = '';
                departmentInput.placeholder = 'Select faculty first...';
                if (facultyDepartmentsMap[selectedFaculty]) {
                    departmentInput.placeholder = 'Start typing department...';
                    facultyDepartmentsMap[selectedFaculty].forEach(function (dept) {
                        const opt = document.createElement('option');
                        opt.value = dept;
                        departmentList.appendChild(opt);
                    });
                }
            };
            facultyInput.addEventListener('change', updateDepartments);
            facultyInput.addEventListener('input', updateDepartments);

            // Trigger once on load in case a value was preserved by browser autofill
            updateDepartments();
        }
    });
</script>
