@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-verify4"
set "REACT_SOURCE=%~dp0frontend\dist"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "APP_ZIP=%ARTIFACT_DIR%\app.zip"
set "REACT_ZIP_LOG=%ARTIFACT_DIR%\react_zip.log"
set "APP_ZIP_LOG=%ARTIFACT_DIR%\app_zip.log"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || exit /b 10
call :package_react_zip || exit /b 11
if not exist "%REACT_ZIP%" exit /b 12
call :package_app_zip || exit /b 13
if not exist "%APP_ZIP%" exit /b 14
dir "%ARTIFACT_DIR%"
exit /b 0
:package_react_zip
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_react_zip.ps1" -SourceDir "%REACT_SOURCE%" -DestinationZip "%REACT_ZIP%" >"%REACT_ZIP_LOG%" 2>&1
if errorlevel 1 (
  type "%REACT_ZIP_LOG%"
  exit /b 1
)
exit /b 0
:package_app_zip
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_app_zip.ps1" -RootDir "%~dp0." -DestinationZip "%APP_ZIP%" >"%APP_ZIP_LOG%" 2>&1
if errorlevel 1 (
  type "%APP_ZIP_LOG%"
  exit /b 1
)
exit /b 0
