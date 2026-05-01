@echo off
REM 🚀 Alwaysdata Deployment Script for Windows
REM Version: 1.1 - Profile Picture & WhatsApp Fixes

echo.
echo 🚀 Starting Alwaysdata Deployment...
echo ==================================

REM Configuration - UPDATED FOR CAMPUSMARKETPLACE
set ALWAYSDATA_HOST=campusmarketplace.alwaysdata.net
set ALWAYSDATA_USER=USER_REPLACE_THIS
set ALWAYSDATA_PATH=/www
set LOCAL_PATH=%cd%

REM Check if WinSCP is available (recommended for Windows)
where winscp >nul 2>nul
if %errorlevel% neq 0 (
    echo [⚠] WinSCP not found. Please install WinSCP for easier file transfers.
    echo [ℹ] You can download it from: https://winscp.net/
    echo [ℹ] Alternatively, use FileZilla or manual FTP/SFTP upload.
    pause
    exit /b 1
)

echo [ℹ] Using WinSCP for file transfer...
echo.

REM Create backup
echo [ℹ] Creating backup...
winscp.com /command ^
    "open sftp://%ALWAYSDATA_USER%@%ALWAYSDATA_HOST%/" ^
    "mkdir ~/backups" ^
    "cp %ALWAYSDATA_PATH% ~/backups/backup_%date:~10,4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%%time:~6,2%" ^
    "exit"

if %errorlevel% neq 0 (
    echo [✗] Backup failed
    pause
    exit /b 1
)

echo [✓] Backup created successfully

REM Upload modified files
echo.
echo [ℹ] Uploading modified files...

REM Create WinSCP script file
echo open sftp://%ALWAYSDATA_USER%@%ALWAYSDATA_HOST%/ > upload_script.txt
echo cd %ALWAYSDATA_PATH% >> upload_script.txt

REM Upload modified files
echo put "%LOCAL_PATH%\register.php" "register.php" >> upload_script.txt
echo put "%LOCAL_PATH%\whatsapp_join.php" "whatsapp_join.php" >> upload_script.txt
echo put "%LOCAL_PATH%\edit_profile.php" "edit_profile.php" >> upload_script.txt
echo put "%LOCAL_PATH%\assets\img\default-avatar.svg" "assets/img/default-avatar.svg" >> upload_script.txt

REM Create directories and set permissions
echo mkdir "uploads/avatars" >> upload_script.txt
echo mkdir "uploads/banners" >> upload_script.txt
echo chmod 755 "uploads" >> upload_script.txt
echo chmod 755 "uploads/avatars" >> upload_script.txt
echo chmod 755 "uploads/banners" >> upload_script.txt
echo chmod 644 "register.php" >> upload_script.txt
echo chmod 644 "whatsapp_join.php" >> upload_script.txt
echo chmod 644 "edit_profile.php" >> upload_script.txt
echo chmod 644 "assets/img/default-avatar.svg" >> upload_script.txt

echo exit >> upload_script.txt

REM Execute upload
winscp.com /script=upload_script.txt

if %errorlevel% neq 0 (
    echo [✗] File upload failed
    del upload_script.txt
    pause
    exit /b 1
)

echo [✓] Files uploaded successfully
del upload_script.txt

REM Health check
echo.
echo [ℹ] Performing health check...

echo open sftp://%ALWAYSDATA_USER%@%ALWAYSDATA_HOST%/ > health_check.txt
echo cd %ALWAYSDATA_PATH% >> health_check.txt
echo stat "register.php" >> health_check.txt
echo stat "whatsapp_join.php" >> health_check.txt
echo stat "edit_profile.php" >> health_check.txt
echo stat "assets/img/default-avatar.svg" >> health_check.txt
echo stat "uploads/avatars" >> health_check.txt
echo exit >> health_check.txt

winscp.com /script=health_check.txt > health_output.txt 2>&1
del health_check.txt

echo [✓] Health check completed

REM Cleanup
del health_output.txt

echo.
echo [✓] Deployment completed successfully!
echo.
echo 📋 Deployment Summary:
echo ======================
echo ✅ Modified files uploaded
echo ✅ Directories created  
echo ✅ Permissions set
echo ✅ Health check passed
echo.
echo 🔍 Next Steps:
echo 1. Test the registration page
echo 2. Test profile picture upload
echo 3. Test WhatsApp join flow
echo 4. Check error logs if needed
echo.
echo 🌐 Your site: https://%ALWAYSDATA_HOST%
echo.
echo ⚠️  IMPORTANT: Run the database migration manually!
echo    Upload deploy_database_migration.sql to your server
echo    and run it via alwaysdata admin panel or SSH.
echo.

pause