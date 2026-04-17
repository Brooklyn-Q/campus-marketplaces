<?php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = getUser($pdo, $_SESSION['user_id']);
if (!$user) { session_destroy(); redirect('login.php'); }

$error = ''; $success = '';

// Faculty options
$faculties = [
    'Faculty of Applied Arts and Technology',
    'Faculty of Applied Sciences',
    'Faculty of Engineering',
    'Faculty of Business Studies',
    'Faculty of Built and Natural Environment',
    'Faculty of Health and Allied Sciences',
    'Faculty of Maritime and Nautical Studies',
    'Faculty of Media Technology and Liberal Studies',
];

// Fields that require admin approval
$approval_fields = ['bio', 'department', 'level', 'hall', 'phone', 'faculty', 'whatsapp', 'instagram', 'linkedin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $bio   = trim($_POST['bio'] ?? '');
    $hall   = trim($_POST['hall'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $wa     = trim($_POST['whatsapp'] ?? '');
    $ig     = trim($_POST['instagram'] ?? '');
    $li     = trim($_POST['linkedin'] ?? '');
    $dept   = trim($_POST['department'] ?? '');
    $level  = $_POST['level'] ?? '';
    $faculty = trim($_POST['faculty'] ?? '');

    // Phone format
    if ($phone && substr($phone, 0, 1) === '0') $phone = '+233' . substr($phone, 1);

    // Profile pic (applies immediately — visual only)
    $pic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
            $pic = 'avatars/' . uniqid('av_') . '.' . $ext;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], 'uploads/' . $pic);
            $pdo->prepare("UPDATE users SET profile_pic=? WHERE id=?")->execute([$pic, $user['id']]);
        }
    }

    // Shop banner (applies immediately — visual only)
    $banner = $user['shop_banner'] ?? null;
    if (isset($_FILES['shop_banner']) && $_FILES['shop_banner']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['shop_banner']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            if (!is_dir('uploads/banners')) mkdir('uploads/banners', 0777, true);
            $banner = 'banners/' . uniqid('bn_') . '.' . $ext;
            move_uploaded_file($_FILES['shop_banner']['tmp_name'], 'uploads/' . $banner);
            $pdo->prepare("UPDATE users SET shop_banner=? WHERE id=?")->execute([$banner, $user['id']]);
        }
    }

    // Compare text fields and create pending requests for changed ones
    $new_values = [
        'bio' => $bio,
        'department' => $dept,
        'level' => $level,
        'hall' => $hall,
        'phone' => $phone,
        'faculty' => $faculty,
        'whatsapp' => $wa,
        'instagram' => $ig,
        'linkedin' => $li,
    ];

    $changes_submitted = 0;
    $insert_stmt = $pdo->prepare("INSERT INTO profile_edit_requests (user_id, field_name, old_value, new_value) VALUES (?,?,?,?)");
    // Cancel any existing pending requests for this user before creating new ones
    $pdo->prepare("UPDATE profile_edit_requests SET status='rejected', resolved_at=NOW() WHERE user_id=? AND status='pending'")->execute([$user['id']]);

    foreach ($new_values as $field => $new_val) {
        $old_val = $user[$field] ?? '';
        if ((string)$new_val !== (string)$old_val) {
            $insert_stmt->execute([$user['id'], $field, $old_val, $new_val]);
            $changes_submitted++;
        }
    }

    if ($changes_submitted > 0) {
        $success = "✅ Your profile changes ({$changes_submitted} field" . ($changes_submitted > 1 ? 's' : '') . ") have been submitted for admin approval.";
    } else {
        $success = "No changes detected. Your profile is up to date.";
    }

    $user = getUser($pdo, $user['id']); // refresh for images
}

// Fetch any pending edits for this user
$pending_edits = [];
try {
    $pe_stmt = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $pe_stmt->execute([$user['id']]);
    $pending_edits = $pe_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) { /* table may not exist yet */ }

require_once 'includes/header.php';
?>

<div class="glass form-container fade-in" style="max-width:650px;">
    <h2 class="mb-3">Edit Profile</h2>

    <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Pending Edits Notice -->
    <?php if(count($pending_edits) > 0): ?>
    <div class="alert alert-warning" style="border-radius:14px; margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
            <span style="font-size:1.2rem;">⏳</span>
            <strong>Pending Approval</strong>
        </div>
        <p style="font-size:0.85rem; margin-bottom:0.75rem; color:var(--text-main);">The following changes are awaiting admin review:</p>
        <?php foreach($pending_edits as $pe): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0.6rem; background:rgba(0,0,0,0.03); border-radius:8px; margin-bottom:0.3rem; font-size:0.82rem;">
                <span style="font-weight:600; text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $pe['field_name'])) ?></span>
                <span style="color:var(--text-muted);">"<?= htmlspecialchars(mb_strimwidth($pe['old_value'] ?: '(empty)', 0, 20, '…')) ?>" → "<span style="color:var(--primary); font-weight:600;"><?= htmlspecialchars(mb_strimwidth($pe['new_value'] ?: '(empty)', 0, 20, '…')) ?></span>"</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group text-center">
            <?php if($user['profile_pic']): ?>
                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['profile_pic'])) ?>" class="profile-pic profile-pic-lg mb-2" alt="Profile">
            <?php endif; ?>
            <label>Profile Photo <small style="color:var(--text-muted);">(updates instantly)</small></label>
            <input type="file" name="profile_pic" class="form-control" accept="image/*">
        </div>

        <div class="form-group">
            <label>Faculty *</label>
            <select name="faculty" class="form-control" required>
                <option value="">— Choose Faculty —</option>
                <?php foreach($faculties as $f): ?>
                    <option value="<?= $f ?>" <?= ($user['faculty'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Bio / Slogan</label>
            <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Level</label>
                <select name="level" class="form-control">
                    <option value="">Select</option>
                    <?php foreach(['100','200','300','400','BTech'] as $l): ?>
                        <option value="<?= $l ?>" <?= ($user['level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Hall / Residence</label>
                <input type="text" name="hall" class="form-control" value="<?= htmlspecialchars($user['hall'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
        </div>

        <h4 class="mb-2 mt-2">Social Links</h4>
        <div class="form-row">
            <div class="form-group">
                <label>WhatsApp Number</label>
                <input type="text" name="whatsapp" class="form-control" value="<?= htmlspecialchars($user['whatsapp'] ?? '') ?>" placeholder="233241234567">
            </div>
            <div class="form-group">
                <label>Instagram Handle</label>
                <input type="text" name="instagram" class="form-control" value="<?= htmlspecialchars($user['instagram'] ?? '') ?>" placeholder="username">
            </div>
        </div>
        <div class="form-group">
            <label>LinkedIn</label>
            <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($user['linkedin'] ?? '') ?>">
        </div>

        <?php if(isSeller()): ?>
        <div class="form-group">
            <label>Shop Banner Image <small style="color:var(--text-muted);">(updates instantly)</small></label>
            <?php if(!empty($user['shop_banner'])): ?>
                <img src="<?= getAssetUrl('uploads/' . htmlspecialchars($user['shop_banner'])) ?>" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;" alt="Banner">
            <?php endif; ?>
            <input type="file" name="shop_banner" class="form-control" accept="image/*">
        </div>
        <?php endif; ?>

        <div style="background:rgba(0,113,227,0.05); border:1px solid rgba(0,113,227,0.15); border-radius:12px; padding:0.8rem 1rem; margin-bottom:1.25rem; font-size:0.82rem; color:var(--text-muted); display:flex; align-items:center; gap:0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0071e3" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Text field changes require admin approval. Image uploads apply immediately.
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Submit Changes for Approval</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
