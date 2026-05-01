@echo off
echo Testing FTP connection to AlwaysData...
echo.

set FTP_SERVER=ftp-campusmarketplace.alwaysdata.net
set FTP_USER=campusmarketplace
set FTP_PASS=Brooklyn@2006

echo Server: %FTP_SERVER%
echo User: %FTP_USER%
echo.

echo Testing with curl...
curl.exe --ftp-pasv --ftp-ssl-reqd --user "%FTP_USER%:%FTP_PASS%" --list-only "ftp://%FTP_SERVER%/www/" --connect-timeout 10

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ FTP connection successful!
) else (
    echo.
    echo ❌ FTP connection failed!
    echo Error code: %ERRORLEVEL%
    echo.
    echo Possible solutions:
    echo 1. Check your alwaysdata password
    echo 2. Enable FTP in alwaysdata admin panel
    echo 3. Verify FTP account is active
)

echo.
pause