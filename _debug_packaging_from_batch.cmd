@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-pack-debug-%RANDOM%%RANDOM%"
set "REACT_SOURCE=%~dp0frontend\dist"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "REACT_ZIP_LOG=%ARTIFACT_DIR%\react_zip.log"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || goto :error
echo ARTIFACT_DIR=%ARTIFACT_DIR%
echo REACT_SOURCE=%REACT_SOURCE%
echo REACT_ZIP=%REACT_ZIP%
call :package_react_zip || goto :error
if not exist "%REACT_ZIP%" (
  echo ERROR: react.zip missing
  if exist "%REACT_ZIP_LOG%" type "%REACT_ZIP_LOG%"
  goto :error
)
echo SUCCESS
Get-ChildItem "%ARTIFACT_DIR%"
exit /b 0

:package_react_zip
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\package_react_zip.ps1" -SourceDir "%REACT_SOURCE%" -DestinationZip "%REACT_ZIP%" >"%REACT_ZIP_LOG%" 2>&1
if errorlevel 1 (
  echo React zip packaging failed:
  if exist "%REACT_ZIP_LOG%" type "%REACT_ZIP_LOG%"
  exit /b 1
)
exit /b 0

:error
echo FAILED
if exist "%ARTIFACT_DIR%" dir "%ARTIFACT_DIR%"
exit /b 1
