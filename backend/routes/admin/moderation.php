<?php
/**
 * Admin Moderation Routes (discounts, disputes, profiles, vacations, announcements, tiers, ads, audit, messages)
 * Consolidated into a single file for maintainability
 */

require_once __DIR__ . '/../../middleware/admin.php';
$auth = authenticate();
requireAdmin($pdo, $auth);

$modResource = $segments[1] ?? ''; // discounts, disputes, profiles, vacations, announcements, tiers, ads, audit, messages
$modId = isset($segments[2]) && is_numeric($segments[2]) ? (int) $segments[2] : null;
$modAction = $segments[3] ?? '';

switch ($modResource) {

// ══════ DISCOUNTS ══════
case 'discounts':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT dr.*, p.title as product_name, u.username as seller_name FROM discount_requests dr JOIN products p ON dr.product_id = p.id JOIN users u ON dr.seller_id = u.id WHERE dr.status = 'pending' ORDER BY dr.created_at DESC");
        jsonResponse(['discounts' => $stmt->fetchAll()]);
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'approve') {
        $req = $pdo->prepare("SELECT * FROM discount_requests WHERE id = ? AND status = 'pending'");
        $req->execute([$modId]);
        $dr = $req->fetch();
        if (!$dr) jsonError('Not found', 404);

        $pdo->prepare("UPDATE discount_requests SET status = 'approved' WHERE id = ?")->execute([$modId]);
        $pdo->prepare("UPDATE products SET price = ?, original_price = ? WHERE id = ?")->execute([$dr['discounted_price'], $dr['original_price'], $dr['product_id']]);
        auditLog($pdo, $auth['user_id'], "Approved discount #$modId", 'discount', $modId);
        jsonSuccess('Discount approved');
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'reject') {
        $pdo->prepare("UPDATE discount_requests SET status = 'rejected' WHERE id = ?")->execute([$modId]);
        auditLog($pdo, $auth['user_id'], "Rejected discount #$modId", 'discount', $modId);
        jsonSuccess('Discount rejected');
    }
    else jsonError('Not found', 404);
    break;

// ══════ DISPUTES ══════
case 'disputes':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT d.*, b.username as complainant_name, t.username as target_name FROM disputes d JOIN users b ON d.complainant_id = b.id JOIN users t ON d.target_id = t.id WHERE d.status='open' ORDER BY d.created_at DESC");
        jsonResponse(['disputes' => $stmt->fetchAll()]);
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'resolve') {
        $body = getJsonBody();
        $note = trim($body['admin_note'] ?? 'Resolved by admin');
        $pdo->prepare("UPDATE disputes SET status='resolved', admin_note=? WHERE id=?")->execute([$note, $modId]);
        auditLog($pdo, $auth['user_id'], "Resolved dispute #$modId", 'dispute', $modId);
        jsonSuccess('Dispute resolved');
    }
    else jsonError('Not found', 404);
    break;

// ══════ PROFILES ══════
case 'profiles':
    if ($method === 'GET') {
        $pe = $pdo->query("SELECT per.*, u.username, u.profile_pic FROM profile_edit_requests per JOIN users u ON per.user_id = u.id WHERE per.status='pending' ORDER BY per.user_id, per.created_at DESC")->fetchAll();
        $grouped = [];
        foreach ($pe as $r) {
            if (!isset($grouped[$r['user_id']])) $grouped[$r['user_id']] = ['user_id' => $r['user_id'], 'username' => $r['username'], 'profile_pic' => $r['profile_pic'], 'edits' => []];
            $grouped[$r['user_id']]['edits'][] = $r;
        }
        jsonResponse(['profile_requests' => array_values($grouped)]);
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'approve') {
        $req = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE id = ? AND status = 'pending'");
        $req->execute([$modId]);
        $pr = $req->fetch();
        if (!$pr) jsonError('Not found', 404);

        global $ALLOWED_PROFILE_FIELDS;
        if (in_array($pr['field_name'], $ALLOWED_PROFILE_FIELDS)) {
            // Use CASE statement instead of dynamic field interpolation
            $allowedFields = implode("','", $ALLOWED_PROFILE_FIELDS);
            if ($pr['field_name'] === 'bio' || $pr['field_name'] === 'phone' || $pr['field_name'] === 'location' || $pr['field_name'] === 'department' || $pr['field_name'] === 'level') {
                $sql = match($pr['field_name']) {
                    'bio' => "UPDATE users SET bio = ? WHERE id = ?",
                    'phone' => "UPDATE users SET phone = ? WHERE id = ?",
                    'location' => "UPDATE users SET location = ? WHERE id = ?",
                    'department' => "UPDATE users SET department = ? WHERE id = ?",
                    'level' => "UPDATE users SET level = ? WHERE id = ?",
                    default => null
                };
                if ($sql) $pdo->prepare($sql)->execute([$pr['new_value'], $pr['user_id']]);
            }
        }
        $pdo->prepare("UPDATE profile_edit_requests SET status='approved', admin_id=?, resolved_at=NOW() WHERE id=?")->execute([$auth['user_id'], $modId]);
        auditLog($pdo, $auth['user_id'], "Approved profile edit #$modId", 'profile', $modId);
        jsonSuccess('Profile edit approved');
    }
    // Approve all for a user: PUT /admin/profiles/user/:userId/approve-all
    elseif ($method === 'PUT' && $param === 'user' && $modAction === 'approve-all') {
        $uid = (int) ($segments[3] ?? 0);
        if (!$uid) jsonError('User ID required');
        global $ALLOWED_PROFILE_FIELDS;
        $reqs = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE user_id=? AND status='pending'");
        $reqs->execute([$uid]);
        foreach ($reqs->fetchAll() as $pr) {
            if (in_array($pr['field_name'], $ALLOWED_PROFILE_FIELDS)) {
                // Use CASE statement instead of dynamic field interpolation
                if ($pr['field_name'] === 'bio' || $pr['field_name'] === 'phone' || $pr['field_name'] === 'location' || $pr['field_name'] === 'department' || $pr['field_name'] === 'level') {
                    $sql = match($pr['field_name']) {
                        'bio' => "UPDATE users SET bio = ? WHERE id = ?",
                        'phone' => "UPDATE users SET phone = ? WHERE id = ?",
                        'location' => "UPDATE users SET location = ? WHERE id = ?",
                        'department' => "UPDATE users SET department = ? WHERE id = ?",
                        'level' => "UPDATE users SET level = ? WHERE id = ?",
                        default => null
                    };
                    if ($sql) $pdo->prepare($sql)->execute([$pr['new_value'], $pr['user_id']]);
                }
            }
            $pdo->prepare("UPDATE profile_edit_requests SET status='approved', admin_id=?, resolved_at=NOW() WHERE id=?")->execute([$auth['user_id'], $pr['id']]);
        }
        auditLog($pdo, $auth['user_id'], "Bulk approved profiles for user #$uid", 'profile', $uid);
        jsonSuccess('All profile edits approved');
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'reject') {
        $pdo->prepare("UPDATE profile_edit_requests SET status='rejected', admin_id=?, resolved_at=NOW() WHERE id=?")->execute([$auth['user_id'], $modId]);
        jsonSuccess('Profile edit rejected');
    }
    else jsonError('Not found', 404);
    break;

// ══════ VACATIONS ══════
case 'vacations':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT v.*, u.username FROM vacation_requests v JOIN users u ON v.seller_id = u.id WHERE v.status='pending' ORDER BY v.created_at DESC");
        jsonResponse(['vacations' => $stmt->fetchAll()]);
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'approve') {
        $req = $pdo->prepare("SELECT seller_id FROM vacation_requests WHERE id = ?");
        $req->execute([$modId]);
        $vr = $req->fetch();
        if ($vr) {
            $pdo->prepare("UPDATE users SET vacation_mode = 1 WHERE id = ?")->execute([$vr['seller_id']]);
            $pdo->prepare("UPDATE vacation_requests SET status = 'approved' WHERE id = ?")->execute([$modId]);
            auditLog($pdo, $auth['user_id'], "Approved vacation for seller #" . $vr['seller_id'], 'vacation', $modId);
        }
        jsonSuccess('Vacation approved');
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'reject') {
        $pdo->prepare("UPDATE vacation_requests SET status = 'rejected' WHERE id = ?")->execute([$modId]);
        jsonSuccess('Vacation rejected');
    }
    else jsonError('Not found', 404);
    break;

// ══════ ANNOUNCEMENTS ══════
case 'announcements':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 20");
        jsonResponse(['announcements' => $stmt->fetchAll()]);
    }
    elseif ($method === 'POST') {
        $body = getJsonBody();
        $msg = trim($body['message'] ?? '');
        $type = $body['type'] ?? 'info';
        if (!$msg) jsonError('Message required');
        $pdo->prepare("INSERT INTO announcements (admin_id, message, type) VALUES (?, ?, ?)")->execute([$auth['user_id'], $msg, $type]);
        auditLog($pdo, $auth['user_id'], "Published announcement: " . substr($msg, 0, 50));
        jsonSuccess('Announcement published');
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'deactivate') {
        $pdo->prepare("UPDATE announcements SET is_active = 0 WHERE id = ?")->execute([$modId]);
        jsonSuccess('Deactivated');
    }
    elseif ($method === 'DELETE' && $modId) {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$modId]);
        jsonSuccess('Deleted');
    }
    else jsonError('Not found', 404);
    break;

// ══════ TIERS ══════
case 'tiers':
    if ($method === 'GET') {
        jsonResponse(['tiers' => getAccountTiers($pdo)]);
    }
    elseif ($method === 'PUT') {
        $body = getJsonBody();
        foreach (['basic', 'pro', 'premium'] as $t) {
            if (!isset($body[$t])) continue;
            $d = $body[$t];
            $pdo->prepare("UPDATE account_tiers SET product_limit=?, images_per_product=?, price=?, duration=?, badge=?, ads_boost=? WHERE tier_name=?")
                ->execute([
                    (int)($d['product_limit'] ?? 2),
                    (int)($d['images_per_product'] ?? 1),
                    (float)($d['price'] ?? 0),
                    $d['duration'] ?? 'forever',
                    $d['badge'] ?? '#0071e3',
                    (int)($d['ads_boost'] ?? 0),
                    $t
                ]);
        }
        auditLog($pdo, $auth['user_id'], "Updated tier settings");
        jsonSuccess('Tiers updated');
    }
    else jsonError('Not found', 404);
    break;

// ══════ ADS ══════
case 'ads':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM ad_placements ORDER BY created_at DESC");
        jsonResponse(['ads' => $stmt->fetchAll()]);
    }
    elseif ($method === 'POST') {
        $body = getJsonBody();
        $pdo->prepare("INSERT INTO ad_placements (title, image_url, link_url, placement) VALUES (?, ?, ?, ?)")
            ->execute([$body['title'] ?? '', $body['image_url'] ?? '', $body['link_url'] ?? '', $body['placement'] ?? 'homepage']);
        jsonSuccess('Ad created');
    }
    elseif ($method === 'PUT' && $modId && $modAction === 'toggle') {
        $pdo->prepare("UPDATE ad_placements SET is_active = NOT is_active WHERE id = ?")->execute([$modId]);
        jsonSuccess('Ad toggled');
    }
    elseif ($method === 'DELETE' && $modId) {
        $pdo->prepare("DELETE FROM ad_placements WHERE id = ?")->execute([$modId]);
        jsonSuccess('Ad deleted');
    }
    else jsonError('Not found', 404);
    break;

// ══════ AUDIT ══════
case 'audit':
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT a.*, u.username as admin_name FROM audit_log a JOIN users u ON a.admin_id = u.id ORDER BY a.created_at DESC LIMIT 50");
        jsonResponse(['logs' => $stmt->fetchAll()]);
    }
    else jsonError('Not found', 404);
    break;

// ══════ MESSAGES (surveillance) ══════
case 'messages':
    if ($method === 'GET') {
        $u1 = (int) getQueryParam('u1', 0);
        $u2 = (int) getQueryParam('u2', 0);
        if ($u1 && $u2) {
            $stmt = $pdo->prepare("SELECT m.*, s.username as sender_name, r.username as receiver_name FROM messages m JOIN users s ON m.sender_id = s.id JOIN users r ON m.receiver_id = r.id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC LIMIT 200");
            $stmt->execute([$u1, $u2, $u2, $u1]);
            jsonResponse(['messages' => $stmt->fetchAll()]);
        }
        // List recent conversations
        $stmt = $pdo->query("SELECT m.sender_id, m.receiver_id, s.username as sender_name, r.username as receiver_name, m.message, m.created_at FROM messages m JOIN users s ON m.sender_id = s.id JOIN users r ON m.receiver_id = r.id ORDER BY m.created_at DESC LIMIT 50");
        jsonResponse(['recent_messages' => $stmt->fetchAll()]);
    }
    else jsonError('Not found', 404);
    break;

default:
    jsonError('Admin resource not found', 404);
}
