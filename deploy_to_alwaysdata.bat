@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "DEPLOY_ROOT=%~dp0"
set "ARTIFACT_BASE=%DEPLOY_ROOT%.deploy-artifacts"
set "ARTIFACT_DIR=%ARTIFACT_BASE%\run-%RANDOM%%RANDOM%"
set "REACT_SOURCE=%DEPLOY_ROOT%frontend\dist"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "APP_ZIP=%ARTIFACT_DIR%\app.zip"
set "UNZIP_PHP=%ARTIFACT_DIR%\unzip.php"
set "UNZIP_REACT_PHP=%ARTIFACT_DIR%\unzip_react.php"
set "REACT_ZIP_LOG=%ARTIFACT_DIR%\react_zip.log"
set "APP_ZIP_LOG=%ARTIFACT_DIR%\app_zip.log"
set "DEPLOY_MODE=full"
set "DEPLOY_SECRET=Brooklyn@2005"
set "KEEP_ARTIFACTS=%DEPLOY_KEEP_ARTIFACTS%"
set "SKIP_INSTALL=%DEPLOY_SKIP_INSTALL%"
set "SKIP_BUILD=%DEPLOY_SKIP_BUILD%"
set "SKIP_CLOUDINARY=%DEPLOY_SKIP_CLOUDINARY%"
set "SKIP_REMOTE=%DEPLOY_SKIP_REMOTE%"

:parse_args
if "%~1"=="" goto :args_done
if /i "%~1"=="--verify-package" (
    set "DEPLOY_MODE=verify-package"
    set "KEEP_ARTIFACTS=1"
    set "SKIP_INSTALL=1"
    set "SKIP_BUILD=1"
    set "SKIP_CLOUDINARY=1"
    set "SKIP_REMOTE=1"
) else if /i "%~1"=="--package-only" (
    set "DEPLOY_MODE=package-only"
    set "KEEP_ARTIFACTS=1"
    set "SKIP_REMOTE=1"
) else if /i "%~1"=="--keep-artifacts" (
    set "KEEP_ARTIFACTS=1"
) else (
    echo ERROR: Unknown deploy option %~1
    goto :error
)
shift
goto :parse_args

:args_done
if not defined KEEP_ARTIFACTS set "KEEP_ARTIFACTS=0"
if not defined SKIP_INSTALL set "SKIP_INSTALL=0"
if not defined SKIP_BUILD set "SKIP_BUILD=0"
if not defined SKIP_CLOUDINARY set "SKIP_CLOUDINARY=0"
if not defined SKIP_REMOTE set "SKIP_REMOTE=0"

echo Deploy mode: %DEPLOY_MODE%
echo Artifact directory: %ARTIFACT_DIR%

if not exist "%ARTIFACT_BASE%" mkdir "%ARTIFACT_BASE%" || goto :error
if "%SKIP_REMOTE%"=="1" goto :artifact_dir_ready
if defined ALWAYSDATA_PASSWORD (
    set "ad_pass=%ALWAYSDATA_PASSWORD%"
) else (
    set /p ad_pass="Enter your AlwaysData password: "
)

:artifact_dir_ready

if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || goto :error

:: Build React frontend with legacy peer dependencies
if "%SKIP_BUILD%"=="1" (
    echo Skipping React frontend build.
) else (
    echo Building React frontend...
    if exist frontend\public\assets\dist rd /s /q frontend\public\assets\dist >nul 2>&1
    pushd frontend
    set "npm_config_cache=%DEPLOY_ROOT%frontend\.npm-cache"
    if "%SKIP_INSTALL%"=="1" (
        echo Skipping npm ci.
    ) else (
        call npm ci --legacy-peer-deps --no-audit --no-fund || goto :error_pop_frontend
    )
    call npm run build || goto :error_pop_frontend
    popd
)

:: Upload assets to Cloudinary and rewrite URLs
if "%SKIP_CLOUDINARY%"=="1" (
    echo Skipping Cloudinary upload and URL replacement.
) else (
    echo Uploading assets to Cloudinary...
    node frontend\upload_to_cloudinary.js || goto :error
    node frontend\replace_urls.js || goto :error
)

:: Package the React build (now with Cloudinary URLs) into a zip
echo Packaging React build (react.zip)...
if not exist "%REACT_SOURCE%" (
    echo ERROR: frontend\dist not found ^(React build failed^)
    goto :error
)
call :package_react_zip || goto :error
if not exist "%REACT_ZIP%" (
    echo ERROR: react.zip was not created
    if exist "%REACT_ZIP_LOG%" type "%REACT_ZIP_LOG%"
    goto :error
)
for %%I in ("%REACT_ZIP%") do echo React package ready: %%~fI ^(%%~zI bytes^)

:: Package the PHP application
echo Packaging the full PHP application (app.zip)...
call :package_app_zip || goto :error
if not exist "%APP_ZIP%" (
    echo ERROR: app.zip was not created
    if exist "%APP_ZIP_LOG%" type "%APP_ZIP_LOG%"
    goto :error
)
for %%I in ("%APP_ZIP%") do echo PHP package ready: %%~fI ^(%%~zI bytes^)

:: Verify critical legacy admin files exist in app.zip before upload
echo Verifying app.zip includes legacy admin files...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "Add-Type -AssemblyName System.IO.Compression.FileSystem; $zip = [IO.Compression.ZipFile]::OpenRead('%APP_ZIP%'); try { " ^
    "$entry = $zip.Entries | Where-Object { $_.FullName -match '(^|[\\/])legacy[\\/]admin[\\/]header\.php$' } | Select-Object -First 1; " ^
    "if (-not $entry) { Write-Host 'ERROR: legacy/admin/header.php not found in app.zip'; exit 1 } " ^
    "Write-Host 'OK: legacy/admin/header.php found in app.zip' " ^
    "} finally { if ($zip) { $zip.Dispose() } }"
if errorlevel 1 goto :error

if "%SKIP_REMOTE%"=="1" (
    echo Packaging verification completed. Skipping AlwaysData upload and extraction.
    goto :success
)

:: Upload PHP bundle (retry up to 3 times)
echo Uploading app.zip...
set "_retries=0"
:upload_app
curl.exe --noproxy "*" --ssl-reqd --ftp-pasv --retry 3 --retry-delay 5 --speed-limit 1 --speed-time 60 -T "%APP_ZIP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass%
if errorlevel 1 (
    set /a "_retries+=1"
    if %_retries% lss 3 (
        echo app.zip upload failed, retrying ^(%_retries%/3^)...
        timeout /t 5 /nobreak >nul
        goto :upload_app
    )
    goto :error
)

:: Upload React bundle (retry up to 3 times)
echo Uploading react.zip...
set "_retries=0"
:upload_react
curl.exe --noproxy "*" --ssl-reqd --ftp-pasv --retry 3 --retry-delay 5 --speed-limit 1 --speed-time 60 -T "%REACT_ZIP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass%
if errorlevel 1 (
    set /a "_retries+=1"
    if %_retries% lss 3 (
        echo react.zip upload failed, retrying ^(%_retries%/3^)...
        timeout /t 5 /nobreak >nul
        goto :upload_react
    )
    goto :error
)

:: Upload unzip scripts (inject DEPLOY_SECRET into placeholder before uploading)
echo Creating unzip.php...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "[IO.File]::WriteAllText('%UNZIP_PHP%', (Get-Content '%~dp0scripts\alwaysdata_unzip.php' -Raw).Replace('%%%%DEPLOY_SECRET%%%%', '%DEPLOY_SECRET%'), [Text.UTF8Encoding]::new($false))"
if not exist "%UNZIP_PHP%" (
    echo ERROR: unzip.php was not created
    goto :error
)
echo Uploading unzip.php...
curl.exe --noproxy "*" --ssl-reqd --ftp-pasv --retry 3 --retry-delay 5 -T "%UNZIP_PHP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass% || goto :error

echo Creating unzip_react.php...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "[IO.File]::WriteAllText('%UNZIP_REACT_PHP%', (Get-Content '%~dp0scripts\alwaysdata_unzip_react.php' -Raw).Replace('%%%%DEPLOY_SECRET%%%%', '%DEPLOY_SECRET%'), [Text.UTF8Encoding]::new($false))"
if not exist "%UNZIP_REACT_PHP%" (
    echo ERROR: unzip_react.php was not created
    goto :error
)
echo Uploading unzip_react.php...
curl.exe --noproxy "*" --ssl-reqd --ftp-pasv --retry 3 --retry-delay 5 -T "%UNZIP_REACT_PHP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass% || goto :error

:: Extract files on the server (secret required — see alwaysdata.env DEPLOY_SECRET)
echo Extracting PHP files on the server...
set "_extract_out="
for /f "delims=" %%R in ('curl.exe --noproxy "*" "https://campusmarketplace.alwaysdata.net/unzip.php?secret=%DEPLOY_SECRET%"') do set "_extract_out=%%R"
echo %_extract_out%
echo %_extract_out% | findstr /i /c:"SUCCESS" >nul || (echo ERROR: PHP extraction failed: %_extract_out% && goto :error)

echo Extracting React files on the server...
set "_react_out="
for /f "delims=" %%R in ('curl.exe --noproxy "*" "https://campusmarketplace.alwaysdata.net/unzip_react.php?secret=%DEPLOY_SECRET%"') do set "_react_out=%%R"
echo %_react_out%
echo %_react_out% | findstr /i /c:"SUCCESS" >nul || (echo ERROR: React extraction failed: %_react_out% && goto :error)

echo.
echo ========================================================
echo DEPLOYMENT COMPLETED!
echo ========================================================
echo Site: https://campusmarketplace.alwaysdata.net
echo.
echo SECURITY FIXES NOW LIVE:
echo   #1  reset_admin_password.php deleted
echo   #2  alwaysdata_unzip.php protected with DEPLOY_SECRET
echo   #3  image_proxy.php SSRF fixed
echo   #4  login.php brute-force rate limiting
echo   #5  forgot_password.php rate limiting
echo   #6  SELECT * on users table eliminated
echo   #7  ALTER TABLE removed from add_product.php hot path
echo   #8  Google tokens verified locally
echo   #9  Dev scripts deleted / XSS in notifications fixed
echo   #10 Path traversal in admin API route blocked
echo   #11 File upload MIME-type validation added
echo   #12 Messages IDOR fixed
echo   #13 Open redirect blocked
echo   #14 display_errors off in production
echo   #15 Dev/migration scripts deleted
echo   #16 Admin 2FA setup with QR code support
echo   #17 User-level 2FA and Security Dashboard
echo   #18 Strong password enforcement (12+ chars)
echo   #19 Mandatory Email Verification before account activation
echo   #20 Session Tracking and Device Management
echo   #21 New Login Email Alerts (Anti-phishing)
echo   #22 Global Error Hardening (Hidden live errors + professional fallback)
echo   #23 WhatsApp Channel Verification Code logic
echo   #24 Admin Settings Tool for global configuration
echo   #25 Google Sign-in 2FA and security integration
echo   #26 Fixed email verification and password reset links (AlwaysData /backend fix)
echo   #27 Fixed Admin Settings SQL crash (PostgreSQL compatibility)
echo   #28 Profile edit and product add redirect to dashboard
echo   #29 Global WhatsApp link integration for phone numbers
echo   #30 Automatic Tier Downgrade ^& Vacation Mode Logic
echo   #31 Tier Subscription Ledger for Admin financial records
echo   #32 Paystack-to-Tier synchronization hardening
echo   #33 Manual Admin Upgrade sync with Subscription Ledger
echo   #34 Fixed Tier Subscription table creation for PostgreSQL
echo   #35 Support Lifetime access in Ledger and Dashboard
echo   #36 Unified Tier Duration logic across system (Manual + Automated)
echo   #37 Professional Responsive Admin Tables (Card View on Mobile)
echo   #38 Restored Admin's Personal Seller Dashboard Navigation (Dynamic Deep Fix)
echo   #39 Professional Responsive Seller Dashboard (Mobile Optimization)
echo.
echo UI IMPROVEMENTS NOW LIVE:
echo   - Profile pic preview on registration
echo   - Leaderboard styling and badges
echo   - WhatsApp join form validation
echo   - Default avatar updated to SVG
echo   - Admin 2FA QR code generator
echo   - User Security settings page
echo   - Email Verification flow (Fixed AlwaysData endpoint)
echo   - Active Sessions management UI
echo   - Login security banners
echo   - Professional 500 error screen
echo   - WhatsApp Verification Code UI
echo   - Admin Site Settings dashboard
echo   - Clickable WhatsApp numbers across site
echo   - Auto-redirects after profile/product updates
echo   - Fixed navbar overflow in mobile landscape view
echo   - Fixed all broken WhatsApp links (channel URL + country code handling)
echo   - Updated WhatsApp channel to Campus Marketplace_TTU
echo   - UI Refresh: Removed all emojis, replaced with Lucide SVG icons
echo   - Added Lucide icons for promo tags (Hot Deal, Flash Sale, etc.)
echo   - Star ratings now use filled star SVGs instead of emojis
echo   - Added live promo tag preview with icons in add_product.php
echo   - Fixed session permission issues with local sessions folder
echo   - CSS/JS cache bumped to v1.8
echo   - Confirm Order flow: buyer must order before Phone/WhatsApp unlock
echo   - Phone button now calls seller (tel:) instead of WhatsApp
echo   - Auto chat message sent to seller on order confirmation
echo   - Sellers now see "My Orders" if they buy from other sellers
echo   - Confirm Item Received: hardened with try/catch (no more 500s from email/notification failures)
echo   - Buyers can re-order the same product after a completed order (shows "Order Again")
echo   - Admin: Click username to view full user profile with all registration credentials
echo   - UI refresh: removed glassmorphism from default cards (less AI-generated feel)
echo   - Unified brand palette: all Apple-blue hex values swapped for consistent purple (#7c3aed)
echo   - Fixed missing } in style.css that was nesting desktop CSS inside a mobile media query
echo   - Subscription countdown timer on Dashboard
echo   - Admin Subscription Ledger dashboard
echo.
echo NEXT STEPS:
echo   1. Visit site and test login, register, checkout
echo   2. Rotate secrets: Cloudinary, Paystack, DB password, JWT
echo   3. Check AlwaysData error logs for any PHP errors
echo   4. Verify Tier expiry logic on a test account
echo ========================================================

:success
:: Clean up local temp files
if "%KEEP_ARTIFACTS%"=="1" (
    echo Keeping debug artifacts at: %ARTIFACT_DIR%
) else (
    echo Cleaning up local temp files...
    call :cleanup
)

echo Deployment finished!
if not "%SKIP_REMOTE%"=="1" pause
exit /b 0

:package_react_zip
echo [react.zip] Source: %REACT_SOURCE%
echo [react.zip] Output: %REACT_ZIP%
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_react_zip.ps1" -SourceDir "%REACT_SOURCE%" -DestinationZip "%REACT_ZIP%" >"%REACT_ZIP_LOG%" 2>&1
if errorlevel 1 (
    echo React zip packaging failed. Log: %REACT_ZIP_LOG%
    if exist "%REACT_ZIP_LOG%" type "%REACT_ZIP_LOG%"
    exit /b 1
)
exit /b 0

:package_app_zip
echo [app.zip] Root: %DEPLOY_ROOT%
echo [app.zip] Output: %APP_ZIP%
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_app_zip.ps1" -RootDir "%~dp0." -DestinationZip "%APP_ZIP%" >"%APP_ZIP_LOG%" 2>&1
if errorlevel 1 (
    echo App zip packaging failed. Log: %APP_ZIP_LOG%
    if exist "%APP_ZIP_LOG%" type "%APP_ZIP_LOG%"
    exit /b 1
)
exit /b 0

:error_pop_frontend
popd
goto :error

:cleanup
if defined ARTIFACT_DIR if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
exit /b 0

:error
echo Deployment failed.
if defined ARTIFACT_DIR if exist "%ARTIFACT_DIR%" echo Debug artifacts kept at: %ARTIFACT_DIR%
if not "%SKIP_REMOTE%"=="1" pause
exit /b 1

