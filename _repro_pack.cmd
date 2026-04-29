@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-packrepro"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || goto :error
echo Uploading assets to Cloudinary...
set CLOUDINARY_DRY_RUN=
node frontend\upload_to_cloudinary.js || goto :error
node frontend\replace_urls.js || goto :error
echo Packaging React build (react.zip)...
pushd frontend\dist || goto :error
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
:error_pop_dist
popd
goto :error
:error
echo FAIL errorlevel=%ERRORLEVEL%
if exist "%ARTIFACT_DIR%" dir "%ARTIFACT_DIR%"
exit /b 1
