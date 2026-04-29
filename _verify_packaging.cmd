@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-verify"
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
set "APP_ZIP=%ARTIFACT_DIR%\app.zip"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%" >nul 2>&1
mkdir "%ARTIFACT_DIR%" || exit /b 10
pushd frontend\dist || exit /b 11
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference = 'Stop';" ^
  "$items = Get-ChildItem -Force | Select-Object -ExpandProperty Name;" ^
  "if (-not $items -or $items.Count -eq 0) { throw 'frontend/dist is empty'; }" ^
  "Compress-Archive -Path $items -DestinationPath $env:REACT_ZIP -Force" || exit /b 12
popd
if not exist "%REACT_ZIP%" exit /b 13
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference = 'Stop';" ^
  "$paths = @('admin','api','assets','backend','components','includes','lib','uploads','alwaysdata.env','alwaysdata.htaccess');" ^
  "$paths += Get-ChildItem -LiteralPath . -Filter '*.php' | Select-Object -ExpandProperty Name;" ^
  "$existing = $paths | Where-Object { Test-Path $_ };" ^
  "if (-not $existing -or $existing.Count -eq 0) { throw 'No PHP deployment files found'; }" ^
  "Compress-Archive -Path $existing -DestinationPath $env:APP_ZIP -Force" || exit /b 14
if not exist "%APP_ZIP%" exit /b 15
dir "%ARTIFACT_DIR%"
exit /b 0
