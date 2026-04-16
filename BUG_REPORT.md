# 🐛 Campus Marketplace - Deep Scan Bug Report
**Date:** 2026-04-16  
**Severity Levels:** 🔴 Critical | 🟠 High | 🟡 Medium | 🔵 Low

---

## 🔴 CRITICAL BUGS

### 1. **Missing Authentication in api/discount.php** (CRITICAL - SECURITY)
**File:** `api/discount.php:38`  
**Issue:** The `get_products` endpoint returns ALL products without any authentication check.
```php
case 'get_products':
    $stmt = $pdo->query("SELECT id, title AS name, price, 
        COALESCE(discount_percent, 0) AS discount, status 
        FROM products ORDER BY created_at DESC");
```
**Risk:** Unauthenticated users can query product data. Should validate user authentication.  
**Fix:** Add `if (!isLoggedIn()) { http_response_code(401); exit; }` at the top.

---

### 2. **Missing Admin Authorization Check in api/discount.php** (CRITICAL - PRIVILEGE ESCALATION)
**File:** `api/discount.php:102-177`  
**Issue:** Endpoints `get_pending`, `approve`, and `reject` have NO admin role verification. Any logged-in user can approve/reject discounts.
```php
case 'get_pending':
    // No isAdmin() check!
case 'approve':
    // No isAdmin() check!
```
**Risk:** Any seller/buyer can approve their own discount requests or manipulate others' discounts.  
**Fix:** Add `if (!isAdmin()) { http_response_code(403); exit; }` to these endpoints.

---

### 3. **SQL Injection in db.php getBadgeHtml()** (CRITICAL)
**File:** `db.php:191`  
**Issue:** User-controlled `$color` variable inserted directly into CSS inline style without proper escaping.
```php
$bg = match($color) { 
    'gold' => 'linear-gradient(135deg, #ff9f0a 0%, #d4af37 100%)', 
    'silver' => 'linear-gradient(135deg, #8b939a 0%, #5d6d7e 100%)', 
    'blue' => 'linear-gradient(135deg, #0071e3 0%, #0056b3 100%)',
    default => $color // Support hex codes in DB - DANGER!
};
...
return "<span class='badge' style='background:$bg; ...";
```
**Risk:** Attacker can inject CSS code via the database, leading to XSS attacks.  
**Fix:** Use `htmlspecialchars()` on `$bg` in the return statement.

---

### 4. **Duplicate Quantity Field in add_product.php** (HIGH - DATA INTEGRITY)
**File:** `add_product.php:119 & 141`  
**Issue:** The form has TWO quantity input fields with the same name `quantity`:
```html
Line 119: <input type="number" name="quantity" class="form-control" value="1" min="1" required>
Line 141: <input type="number" name="quantity" class="form-control" required min="1" value="1">
```
**Risk:** Only the last quantity field value is submitted. First field is ignored, causing confusion.  
**Fix:** Remove one of the duplicate fields (line 119 appears to be the one under "Quantity").

---

## 🟠 HIGH SEVERITY BUGS

### 5. **Missing Input Validation on Profile Picture Upload** (HIGH - PATH TRAVERSAL)
**File:** `register.php:54-60`  
**Issue:** Profile picture filename not validated for path traversal. Using `uniqid()` is insufficient if filename can contain directory separators.
```php
$ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
if (in_array($ext, ['jpg','jpeg','png','webp'])) {
    if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
    $pic = 'avatars/' . uniqid('av_') . '.' . $ext;
    move_uploaded_file($_FILES['profile_pic']['tmp_name'], 'uploads/' . $pic);
```
**Risk:** While the filename construction looks safe (using uniqid), the uploaded file can still contain EXIF data with malicious payloads.  
**Fix:** Run images through `imagecreatefromjpeg()` or similar to strip EXIF data.

---

### 6. **Incomplete Error Logging in api/paystack_verify.php** (HIGH - AUDIT TRAIL)
**File:** `api/paystack_verify.php:74`  
**Issue:** Database errors are logged but payment verification failures aren't audited properly.
```php
} catch(Exception $e) {
    $pdo->rollBack();
    error_log('paystack_verify.php DB error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
}
```
**Risk:** No record of failed payment verifications. Difficult to debug payment issues later.  
**Fix:** Log to database transactions table with `status='failed'` reason.

---

### 7. **Race Condition in Referral Bonus Logic** (HIGH - DATA INTEGRITY)
**File:** `register.php:76-83`  
**Issue:** Multiple database writes without atomicity. If one fails, system is in inconsistent state.
```php
$pdo->prepare("UPDATE users SET balance = balance + 5.00 WHERE id = ?")->execute([$referred_by]);
$pdo->prepare("INSERT INTO transactions ...")->execute([...]);
// If next execute fails, first updates already committed
$pdo->prepare("UPDATE users SET balance = balance + 2.00 WHERE id = ?")->execute([$user_id]);
```
**Risk:** Imbalance between user balance and transactions table.  
**Fix:** Already wrapped in transaction (line 42), but ensure ALL referral operations complete successfully.

---

### 8. **Missing File Upload Validation in chat.php** (HIGH - ARBITRARY FILE UPLOAD)
**File:** `api/chat.php:62-64`  
**Issue:** File size not validated. Only extension checked, but MIME type not verified.
```php
if (in_array($ext, array_merge($allowed_images, $allowed_videos, $allowed_audio))) {
    $newName = 'chat_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], '../uploads/' . $newName)) {
```
**Risk:** Large files bypass server limits. MIME type spoofing (e.g., executable as .jpg).  
**Fix:** Check `mime_content_type()` or `finfo_file()`, and enforce max file size limits.

---

## 🟡 MEDIUM SEVERITY BUGS

### 9. **Array Index Error in api/chat_ai.php** (MEDIUM - POTENTIAL CRASH)
**File:** `api/chat_ai.php:68`  
**Issue:** Unsafe nested array access without checking structure.
```php
$response_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
```
**Risk:** If Gemini API returns unexpected structure, `$data` could be `null`, causing fatal error.  
**Fix:** Validate API response structure with proper error handling.

---

### 10. **No CSRF Token Protection** (MEDIUM - SECURITY)
**Files:** `register.php`, `login.php`, `add_product.php`, etc.
**Issue:** POST forms don't use CSRF tokens.
```php
<form method="POST">  // No csrf_token field
```
**Risk:** Cross-Site Request Forgery attacks possible. Attacker can make authenticated requests on behalf of users.  
**Fix:** Generate and validate CSRF tokens in all forms.

---

### 11. **Weak Password Requirements** (MEDIUM - SECURITY)
**File:** `register.php:31`  
**Issue:** Only 6-character minimum password requirement.
```php
elseif (strlen($password) < 6) { $error = "Password must be at least 6 characters."; }
```
**Risk:** Weak passwords vulnerable to brute force.  
**Fix:** Increase minimum to 8-12 characters and require complexity (uppercase, numbers, symbols).

---

### 12. **Missing CORS Headers on API Endpoints** (MEDIUM)
**File:** `api/chat_ai.php:2-3`  
**Issue:** CORS headers set in `api/discount.php:18-19` but inconsistent across all API files.
```php
header('Access-Control-Allow-Origin: *');  // Only in discount.php
```
**Risk:** Some APIs vulnerable to CORS attacks, others too restrictive.  
**Fix:** Centralize CORS policy; restrict to specific domains if possible.

---

### 13. **SQL Query Without WHERE Clause Check** (MEDIUM - LOGIC ERROR)
**File:** `api/search_suggest.php:12`  
**Issue:** Fetches ALL product titles into memory on every search.
```php
$stmt = $pdo->prepare("SELECT title FROM products WHERE status = 'approved'");
$stmt->execute();
$titles = $stmt->fetchAll(PDO::FETCH_COLUMN);
```
**Risk:** With millions of products, causes memory exhaustion and slow response.  
**Fix:** Use `LIKE` query with LIMIT instead of fetching all titles.

---

## 🔵 LOW SEVERITY BUGS

### 14. **Missing HTTP Security Headers** (LOW)
**Files:** All PHP files  
**Issue:** No X-Frame-Options, X-Content-Type-Options, etc.
**Fix:** Add security headers in common header file or .htaccess.

---

### 15. **Inconsistent Error Messages** (LOW - UX/INFO DISCLOSURE)
**File:** `login.php:39`, `api/chat.php:100`
**Issue:** Generic error messages sometimes expose information.
```php
$error = "Invalid email/username or password.";  // Good - generic
vs
echo json_encode(['error' => 'Invalid file type.']);  // Tells attacker valid types
```

---

### 16. **Session Not Regenerated After Login** (LOW - SECURITY)
**File:** `login.php:23-25`
**Issue:** Should call `session_regenerate_id(true)` after successful login to prevent session fixation.
```php
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
// Missing: session_regenerate_id(true);
```

---

### 17. **Debug Output in Live Code** (LOW - INFO DISCLOSURE)
**File:** `db.php:22`  
**Issue:** Detailed error page shown on production.
```php
die('<!DOCTYPE html>...<p>We are currently optimizing our servers...</p>');
```
**Risk:** Attacker knows the site uses PHP/MySQL.  
**Fix:** Log to file instead, show generic error to user.

---

### 18. **Potential Information Disclosure via Error Logs** (LOW)
**File:** `db.php:28-32`, `includes/db.php` (likely similar)
**Issue:** `.env` file location is predictable and vulnerable if web root is misconfigured.
**Fix:** Ensure `.env` is outside web root.

---

## 📊 SUMMARY TABLE

| Severity | Count | Category | 
|----------|-------|----------|
| 🔴 Critical | 4 | Auth, SQL Injection, Data Integrity |
| 🟠 High | 5 | File Upload, Race Conditions, Audit Trail |
| 🟡 Medium | 5 | CSRF, Weak Passwords, CORS, Performance |
| 🔵 Low | 4 | Headers, Session, Logging |
| **TOTAL** | **18** | - |

---

## 🔧 RECOMMENDED FIXES (Priority Order)

1. ✅ Add `isAdmin()` check to discount API endpoints
2. ✅ Add `isLoggedIn()` check to product listing API
3. ✅ Fix SQL injection in getBadgeHtml() with htmlspecialchars()
4. ✅ Remove duplicate quantity field in add_product.php
5. ✅ Implement CSRF token protection across all forms
6. ✅ Add MIME type validation for file uploads
7. ✅ Increase password minimum to 8 characters
8. ✅ Implement search query optimization
9. ✅ Add security headers
10. ✅ Regenerate session ID after login

---

## 📝 Notes

- Admin authorization checks are properly implemented in `admin/header.php`
- Database connection uses prepared statements (PDO) - good practice for SQL injection prevention
- Most file uploads use reasonable extension whitelisting
- Session handling is generally secure but missing regeneration on login

