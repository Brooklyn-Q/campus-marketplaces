@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-pack-debug2-%RANDOM%%RANDOM%"
set "REACT_SOURCE=%~dp0frontend\dist"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "APP_ZIP=%ARTIFACT_DIR%\app.zip"
set "REACT_ZIP_LOG=%ARTIFACT_DIR%\react_zip.log"
set "APP_ZIP_LOG=%ARTIFACT_DIR%\app_zip.log"
mkdir "%ARTIFACT_DIR%" || goto :error
call :package_react_zip || goto :error
call :package_app_zip || goto :error
if not exist "%REACT_ZIP%" goto :error
if not exist "%APP_ZIP%" goto :error
echo SUCCESS
for %%F in ("%ARTIFACT_DIR%\*") do echo %%~nxF %%~zF
exit /b 0

:package_react_zip
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_react_zip.ps1" -SourceDir "%REACT_SOURCE%" -DestinationZip "%REACT_ZIP%" >"%REACT_ZIP_LOG%" 2>&1
if errorlevel 1 (
  echo React zip packaging failed:
  if exist "%REACT_ZIP_LOG%" type "%REACT_ZIP_LOG%"
  exit /b 1
)
exit /b 0

:package_app_zip
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_app_zip.ps1" -RootDir "%~dp0." -DestinationZip "%APP_ZIP%" >"%APP_ZIP_LOG%" 2>&1
if errorlevel 1 (
  echo App zip packaging failed:
  if exist "%APP_ZIP_LOG%" type "%APP_ZIP_LOG%"
  exit /b 1
)
exit /b 0

:error
echo FAILED
if exist "%ARTIFACT_DIR%" dir "%ARTIFACT_DIR%"
exit /b 1
