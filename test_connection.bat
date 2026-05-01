@echo off
echo Testing connection to alwaysdata...
echo.

REM Configuration - UPDATED FOR CAMPUSMARKETPLACE
set ALWAYSDATA_HOST=campusmarketplace.alwaysdata.net
set ALWAYSDATA_USER=USER_REPLACE_THIS
set ALWAYSDATA_PATH=/www

echo Host: %ALWAYSDATA_HOST%
echo User: %ALWAYSDATA_USER%
echo Path: %ALWAYSDATA_PATH%
echo.

REM Test connection
winscp.com /command ^
    "open sftp://%ALWAYSDATA_USER%@%ALWAYSDATA_HOST%/" ^
    "ls %ALWAYSDATA_PATH%" ^
    "exit"

if %errorlevel% neq 0 (
    echo [✗] Connection failed
    echo Please check your username and password
) else (
    echo [✓] Connection successful!
)

pause