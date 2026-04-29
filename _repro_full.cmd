@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-fullrepro"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || goto :error
echo Building React frontend...
if exist frontend\public\assets\dist rd /s /q frontend\public\assets\dist >nul 2>&1
pushd frontend
set "npm_config_cache=%cd%\.npm-cache"
call npm ci --legacy-peer-deps --no-audit --no-fund || goto :error_pop_frontend
call npm run build || goto :error_pop_frontend
popd
echo Uploading assets to Cloudinary...
set CLOUDINARY_DRY_RUN=1
node frontend\upload_to_cloudinary.js || goto :error
node frontend\replace_urls.js || goto :error
echo Packaging React build (react.zip)...
if not exist frontend\dist (
  echo ERROR: frontend\dist not found (React build failed)
  goto :error
)
pushd frontend\dist
tar.exe -a -c -f "%REACT_ZIP%" * || goto :error_pop_dist
echo TAR_EXIT:%ERRORLEVEL%
popd
if not exist "%REACT_ZIP%" (
  echo ERROR: react.zip was not created
  goto :error
)
echo REACT_ZIP_OK
dir "%ARTIFACT_DIR%"
exit /b 0
:error_pop_frontend
popd
goto :error
:error_pop_dist
popd
goto :error
:error
echo FAIL errorlevel=%ERRORLEVEL%
if exist "%ARTIFACT_DIR%" dir "%ARTIFACT_DIR%"
exit /b 1
