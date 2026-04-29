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
set "REACT_ZIP_LOG=%ARTIFACT_DIR%\react_zip.log"
set "APP_ZIP_LOG=%ARTIFACT_DIR%\app_zip.log"
set "DEPLOY_MODE=full"
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

if "%SKIP_REMOTE%"=="1" (
    echo Packaging verification completed. Skipping AlwaysData upload and extraction.
    goto :success
)

:: Upload PHP bundle
echo Uploading app.zip...
curl.exe --noproxy "*" --ssl-reqd -T "%APP_ZIP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass% || goto :error

:: Upload React bundle
echo Uploading react.zip...
curl.exe --noproxy "*" --ssl-reqd -T "%REACT_ZIP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass% || goto :error

:: Upload unzip script (for PHP extraction)
echo Creating unzip.php...
copy /y "%~dp0scripts\alwaysdata_unzip.php" "%UNZIP_PHP%" >nul
if not exist "%UNZIP_PHP%" (
    echo ERROR: unzip.php was not created
    goto :error
)
echo Uploading unzip.php...
curl.exe --noproxy "*" --ssl-reqd -T "%UNZIP_PHP%" ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass% || goto :error

:: Extract files on the server
echo Extracting PHP files on the server...
curl.exe --noproxy "*" -s https://campusmarketplace.alwaysdata.net/unzip.php

echo Extracting React files on the server...
curl.exe --noproxy "*" -s https://campusmarketplace.alwaysdata.net/unzip_react.php

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
