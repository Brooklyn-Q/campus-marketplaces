@echo off
echo Deploying cache-clearing script to AlwaysData...
echo.

REM Get FTP password from user
set /p FTP_PASSWORD="Enter your AlwaysData FTP password: "

echo.
echo Uploading cache-clearing script...
curl -T "clear_cache.php" "ftp://ftp-campusmarketplace.alwaysdata.net/clear_cache.php" --user "campusmarketplace:%FTP_PASSWORD%" --ftp-create-dirs

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ Cache-clearing script deployed successfully!
    echo.
    echo 🌐 Visit: https://campusmarketplace.alwaysdata.net/clear_cache.php
    echo    Then test your pages with ?v=[timestamp] to bypass cache
    echo.
    echo 📊 Dashboard: https://campusmarketplace.alwaysdata.net/dashboard.php?v=%RANDOM%
    echo 🏆 Leaderboard: https://campusmarketplace.alwaysdata.net/leaderboard.php?v=%RANDOM%
    echo 👥 Admin Users: https://campusmarketplace.alwaysdata.net/admin/users.php?v=%RANDOM%
    echo.
) else (
    echo.
    echo ❌ Failed to upload cache-clearing script
    echo    Check your FTP password and try again
    echo.
)

pause