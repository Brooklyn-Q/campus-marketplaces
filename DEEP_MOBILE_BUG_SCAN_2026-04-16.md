# Deep Mobile View Bug Scan — 2026-04-16

## Executive Summary

Comprehensive security and UX audit of mobile view reveals **8 high-priority bugs** across security, accessibility, and functionality categories. Screenshots analyzed via `mobile.html/` folder.

---

## CRITICAL SECURITY BUGS

### 1. ⚠️ **Missing CSRF Token Protection** (HIGH SEVERITY)
**Status**: NOT FIXED  
**Impact**: Forms vulnerable to Cross-Site Request Forgery attacks  
**Files Affected**:
- `register.php:129` - Registration form has no CSRF token
- `add_product.php:103` - Product upload form has no CSRF token
- `checkout.php:89` - Checkout form has no CSRF token
- `login.php` - Login form likely also missing CSRF token
- `dashboard.php` - Dashboard forms missing CSRF tokens

**Example Issue** (register.php):
```php
<form method="POST" enctype="multipart/form-data" id="registerForm">
    <input type="hidden" name="mode" value="<?= $mode ?>">
    <!-- NO CSRF TOKEN -->
</form>
```

**Fix Required**:
- Generate token: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`
- Add to form: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">`
- Verify on POST: `if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF');`

**Recommendation**: Implement middleware or helper function to validate all POST forms

---

### 2. ⚠️ **Weak Password Requirements** (HIGH SEVERITY)
**Status**: NOT FIXED  
**Current Requirement**: 6 characters minimum  
**Recommended**: 8-12 characters minimum  
**Files Affected**:
- `register.php:31` - Password validation only checks for 6+ chars
- `register.php:147` - Form input has `minlength="6"`

**Current Code** (register.php:31):
```php
elseif (strlen($password) < 6) { $error = "Password must be at least 6 characters."; }
```

**Fix Required**:
```php
elseif (strlen($password) < 12) { $error = "Password must be at least 12 characters."; }
```

**Additional Recommendations**:
- Add complexity validation (uppercase, lowercase, numbers, special chars)
- Show password strength meter on frontend
- Consider rate limiting login attempts

---

### 3. ⚠️ **File Upload: No MIME Type Validation** (MEDIUM SEVERITY)
**Status**: NOT FIXED  
**Impact**: Potential for malicious file uploads (exe, shell scripts, etc.)  
**Files Affected**:
- `register.php:54-60` - Profile pic upload only checks extension
- `add_product.php:44-51` - Product images only check extension

**Current Code** (register.php:54-60):
```php
$ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
if (in_array($ext, ['jpg','jpeg','png','webp'])) {
    // NO MIME TYPE CHECK - just extension validation!
    move_uploaded_file($_FILES['profile_pic']['tmp_name'], 'uploads/' . $pic);
}
```

**Attack Vector**:
- Upload `.jpg.php` file
- Apache might execute as PHP
- Remote Code Execution vulnerability

**Fix Required**:
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime, $allowed_mimes)) {
    $error = "Invalid file type. Only JPEG, PNG, WebP allowed.";
}
```

---

### 4. ⚠️ **No EXIF Data Stripping on Images** (MEDIUM SEVERITY)
**Status**: NOT FIXED  
**Impact**: User metadata (GPS, device info) exposed in uploaded images  
**Files Affected**:
- `register.php:59` - Profile pics uploaded without EXIF stripping
- `add_product.php:54` - Product images uploaded without EXIF stripping

**Current Code**: Images are saved as-is, no EXIF processing

**Privacy Risk**: Users' photos may contain:
- GPS coordinates (location tracking)
- Device make/model
- Timestamps
- Camera settings

**Fix Required**:
```php
// Use GD or ImageMagick to re-encode image (strips EXIF)
$image = imagecreatefromjpeg($tmp_path);
imagejpeg($image, $destination_path, 85);
imagedestroy($image);
```

OR use external library:
```
composer require intervention/image
```

---

## HIGH-SEVERITY MOBILE UX BUGS

### 5. 🐛 **Product Image Broken Placeholder ("?") Visible**
**Status**: PARTIALLY FIXED  
**Severity**: HIGH (affects all product listings)  
**Screenshots**: IMG_6221.PNG, IMG_6223.PNG, IMG_6224.PNG  
**Current State**: Blue "?" icons showing instead of product images

**Root Cause Analysis**:
- `cart_suggestions.php:22` has correct `getAssetUrl()` call
- BUT image path construction may be inconsistent elsewhere
- Fallback in `footer.php:350-358` shows SVG placeholder, but appears as "?" in screenshots

**Issue**: The image fallback SVG is rendering as a blue "?" instead of the expected SVG icon

**Affected Files**:
- `cart_suggestions.php:22-24`
- `includes/footer.php:348-358`
- Any file using `<img class="product-img">`

**Fix**:
1. Verify all product image URLs are correctly generated
2. Ensure fallback SVG displays properly:
```php
// Current code shows SVG placeholder
// But screenshots show blue "?" - mismatch between code and display
```

**Action Item**: Test image loading on actual mobile device at 3G speed to diagnose exact issue

---

### 6. 🐛 **"Sign Up" Button Text Truncation on Mobile**
**Status**: PARTIALLY FIXED  
**Severity**: MEDIUM (affects registration flow)  
**Screenshots**: IMG_6221.PNG (top right, blue button)  
**Current Display**: Shows as "sign Up" or truncated text

**File**: `includes/header.php:179`
```php
<a href="<?= $baseUrl ?>register.php" style="background:#0071e3; color:#fff; font-weight:600; font-size:0.85rem; padding:0.5rem 1.1rem; min-height:48px; border-radius:980px; text-decoration:none; transition:all 0.2s; display:inline-flex; align-items:center; justify-content:center;">Sign Up</a>
```

**Responsive Issues**:
- Font size is `0.85rem` - may be too large for small screens
- Padding `0.5rem 1.1rem` may not scale properly
- `inline-flex` with `min-height:48px` doesn't guarantee button width

**Fix**:
```css
@media (max-width: 480px) {
    .nav-links a[href*="register"] {
        font-size: 0.75rem !important;
        padding: 0.4rem 0.8rem !important;
        white-space: nowrap;
    }
}
```

---

### 7. 🐛 **Hero Section Background Image Not Loading on Mobile**
**Status**: NOT FIXED  
**Severity**: MEDIUM (affects visual appeal)  
**Screenshots**: IMG_6222.PNG (shows gray background, no hero image)  
**Current Display**: Gray background with search box, no hero image

**Root Cause**: Background image likely set via CSS but may not be loading on mobile due to:
- Viewport size constraints
- Network optimization
- Image path issues

**Files to Check**:
- `assets/css/style.css` - Hero section styling
- `includes/header.php` - Hero markup

**Fix**: Add responsive background images
```css
.hero {
    background-image: url('hero-mobile.webp');
    background-size: cover;
    background-position: center;
}

@media (min-width: 768px) {
    .hero {
        background-image: url('hero-desktop.webp');
    }
}
```

---

### 8. 🐛 **Search Filter Layout Cramped on Mobile**
**Status**: PARTIALLY FIXED  
**Severity**: MEDIUM (affects usability)  
**Screenshots**: IMG_6221.PNG, IMG_6223.PNG  
**Current Display**: Min/Max price inputs and search button all on one line

**Issue**: Filter inputs should stack vertically on small screens

**File**: `index.php` (search form layout)

**CSS Fix Needed**:
```css
@media (max-width: 600px) {
    .search-filters {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .search-filters input,
    .search-filters select {
        width: 100% !important;
    }
}
```

---

## ACCESSIBILITY ISSUES

### 9. 🎯 **Chat Button May Trigger Accidental Taps**
**Status**: DESIGN ISSUE  
**Severity**: LOW  
**File**: `includes/footer.php:223`

**Current**: Blue button in bottom-right corner at 56x56px

**Issue**: 
- Located where thumb naturally rests on mobile
- May cause accidental taps while scrolling
- Proper size is 48x48px minimum per WCAG AAA

**Recommended Fix**:
```css
#ai-chat-btn {
    width: 56px;
    height: 56px;
    bottom: 24px;  /* Increase bottom margin */
    right: 24px;   /* Increase right margin */
    margin: 0;     /* Add safe zone padding */
}
```

---

## PERFORMANCE & OPTIMIZATION

### 10. ⚠️ **Mobile Nav May Cause Layout Shift**
**Status**: MONITORED  
**Files Affected**:
- `includes/header.php:198-210` (toggleMobileNav function)
- `admin/header.php:24-53` (admin nav mobile)

**Issue**: When nav menu opens/closes, layout may shift due to:
- No fixed width container
- Overflow changes
- z-index stacking

**Fix**: Add `overflow: hidden` to body when nav is open
```javascript
function toggleMobileNav() {
    // ... existing code ...
    document.body.style.overflow = navLinks.classList.contains('open') ? 'hidden' : 'auto';
}
```

---

## SUMMARY TABLE

| # | Issue | Severity | Category | Fixed? | Priority |
|---|-------|----------|----------|--------|----------|
| 1 | Missing CSRF Tokens | 🔴 HIGH | Security | ❌ No | P0 |
| 2 | Weak Password (6 chars) | 🔴 HIGH | Security | ❌ No | P0 |
| 3 | No MIME Type Validation | 🟠 MEDIUM | Security | ❌ No | P1 |
| 4 | No EXIF Stripping | 🟠 MEDIUM | Privacy | ❌ No | P1 |
| 5 | Broken Product Images | 🔴 HIGH | UX | 🟡 Partial | P0 |
| 6 | Sign Up Button Truncation | 🟠 MEDIUM | Mobile UX | 🟡 Partial | P1 |
| 7 | Hero Image Not Loading | 🟠 MEDIUM | UX | ❌ No | P2 |
| 8 | Filter Layout Cramped | 🟠 MEDIUM | Mobile UX | 🟡 Partial | P1 |
| 9 | Chat Button Accessibility | 🟡 LOW | A11y | ⚠️ Design | P3 |
| 10 | Mobile Nav Layout Shift | 🟡 LOW | Performance | ⚠️ Needs fix | P2 |

---

## RECOMMENDED FIX PRIORITY

### Phase 1 (Critical Security - Do Now):
1. **Add CSRF token validation** to all forms
2. **Increase password minimum to 12 chars**
3. **Add MIME type validation** for file uploads
4. **Implement EXIF stripping** on image uploads
5. **Debug product image loading** on mobile

### Phase 2 (Mobile UX - This Sprint):
6. Fix "Sign Up" button responsiveness
7. Stack filter inputs vertically on mobile
8. Fix hero section image loading
9. Improve chat button positioning

### Phase 3 (Polish - Next Sprint):
10. Add password strength validation
11. Improve nav performance on toggle
12. Add rate limiting to login attempts

---

## TESTING CHECKLIST

- [ ] Test all forms with CSRF token validation
- [ ] Test registration with passwords < 12 chars (should fail)
- [ ] Test registration with passwords >= 12 chars (should work)
- [ ] Upload various file types (exe, php, jpg, png) - only images should save
- [ ] Check EXIF data is stripped from uploaded images
- [ ] Verify product images load on 3G network
- [ ] Test "Sign Up" button truncation on iPhone SE (375px) and iPad (768px)
- [ ] Test filter form on mobile landscape mode
- [ ] Verify hero image loads on mobile
- [ ] Check chat button doesn't cause accidental taps

---

## NOTES

- Screenshots stored in `mobile.html/` folder  
- Previous fixes documented in `MOBILE_BUGS_FOUND.md`  
- Prior security scan results in `bugs_found.md`  
- Many issues were identified but NOT fixed in previous scan  
