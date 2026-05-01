@echo off
title 🚀 Campus Marketplace - Quick Deploy to Alwaysdata
color 0A

echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║         🚀 CAMPUS MARKETPLACE - AUTO DEPLOYMENT               ║
echo ║                                                              ║
echo ║  This script will deploy your profile picture & WhatsApp      ║
echo ║  fixes to campusmarketplace.alwaysdata.net                    ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.

REM Get username from user
set /p USERNAME="Enter your alwaysdata username: "

if "%USERNAME%"=="" (
    echo ❌ Username cannot be empty!
    pause
    exit /b 1
)

echo.
echo 🔧 Configuring deployment with your settings...
echo.

REM Update deploy.bat with username
powershell -Command "(Get-Content deploy.bat) -replace 'USER_REPLACE_THIS', '%USERNAME%' | Set-Content deploy.bat"

REM Update test_connection.bat with username  
powershell -Command "(Get-Content test_connection.bat) -replace 'USER_REPLACE_THIS', '%USERNAME%' | Set-Content test_connection.bat"

echo ✅ Configuration updated!
echo.
echo 📋 What will be deployed:
echo    • register.php (profile picture preview)
echo    • whatsapp_join.php (enhanced validation)
echo    • edit_profile.php (default avatar fix)
echo    • assets/img/default-avatar.svg (new default avatar)
echo.

set /p CONFIRM="Ready to deploy? (Y/N): "
if /i not "%CONFIRM%"=="Y" (
    echo ❌ Deployment cancelled
    pause
    exit /b 1
)

echo.
echo 🚀 Starting deployment...
echo.

REM Check if WinSCP is installed
where winscp >nul 2>nul
if %errorlevel% neq 0 (
    echo ❌ WinSCP not found!
    echo 📥 Please download WinSCP from: https://winscp.net/
    echo 📥 Install it and run this script again.
    echo.
    pause
    exit /b 1
)

echo ✅ WinSCP found
echo.

REM Test connection first
echo 🔍 Testing connection to alwaysdata...
winscp.com /command ^
    "open sftp://%USERNAME%@campusmarketplace.alwaysdata.net/" ^
    "ls /www" ^
    "exit" > connection_test.txt 2>&1

findstr "access denied" connection_test.txt >nul
if %errorlevel%==0 (
    echo ❌ Connection failed - wrong username or password
    echo Please check your alwaysdata credentials
    del connection_test.txt
    pause
    exit /b 1
)

echo ✅ Connection successful!
del connection_test.txt

REM Create WinSCP script for deployment
echo open sftp://%USERNAME%@campusmarketplace.alwaysdata.net/ > upload_script.txt
echo cd /www >> upload_script.txt

REM Upload modified files
echo put "%cd%\register.php" "register.php" >> upload_script.txt
echo put "%cd%\whatsapp_join.php" "whatsapp_join.php" >> upload_script.txt
echo put "%cd%\edit_profile.php" "edit_profile.php" >> upload_script.txt
echo put "%cd%\assets\img\default-avatar.svg" "assets/img/default-avatar.svg" >> upload_script.txt

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

echo 📤 Uploading files...
winscp.com /script=upload_script.txt /ini=nul /log=deploy_log.txt

if %errorlevel% neq 0 (
    echo ❌ File upload failed!
    echo Check deploy_log.txt for details
    del upload_script.txt
    pause
    exit /b 1
)

echo ✅ Files uploaded successfully!
del upload_script.txt

echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║                     ✅ DEPLOYMENT COMPLETE!                   ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
echo 🎉 Files deployed to: https://campusmarketplace.alwaysdata.net
echo.
echo 📋 NEXT STEPS:
echo 1️⃣  Run the database migration:
echo    • Go to your alwaysdata admin panel
echo    • Navigate to PostgreSQL → SQL interface  
echo    • Copy contents of: deploy_database_migration.sql
echo    • Execute the script
echo.
echo 2️⃣  Test your fixes:
echo    • Profile picture preview on registration
echo    • WhatsApp join flow with validation
echo    • Default avatar display
echo.
echo 📁 Log file saved as: deploy_log.txt
echo.

set /p MIGRATION="Have you run the database migration? (Y/N): "
if /i not "%MIGRATION%"=="Y" (
    echo.
    echo ⚠️  IMPORTANT: Don't forget to run the database migration!
    echo    File: deploy_database_migration.sql
    echo.
)

echo 🌐 Your site: https://campusmarketplace.alwaysdata.net
echo.
echo Press any key to exit...
pause >nul