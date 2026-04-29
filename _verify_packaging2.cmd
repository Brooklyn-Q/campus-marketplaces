@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-verify2"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "APP_ZIP=%ARTIFACT_DIR%\app.zip"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || exit /b 10
pushd frontend\dist || exit /b 11
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_react_zip.ps1" -SourceDir "%cd%" -DestinationZip "%REACT_ZIP%" || exit /b 12
popd
if not exist "%REACT_ZIP%" exit /b 13
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_app_zip.ps1" -RootDir "%~dp0" -DestinationZip "%APP_ZIP%" || exit /b 14
if not exist "%APP_ZIP%" exit /b 15
dir "%ARTIFACT_DIR%"
exit /b 0
