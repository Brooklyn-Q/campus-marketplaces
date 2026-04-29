@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "ARTIFACT_DIR=%TEMP%\marketplace-deploy-repro"
if exist "%ARTIFACT_DIR%" rd /s /q "%ARTIFACT_DIR%"
mkdir "%ARTIFACT_DIR%" || exit /b 10
set "REACT_ZIP=%ARTIFACT_DIR%\react.zip"
echo ARTIFACT_DIR=%ARTIFACT_DIR%
echo REACT_ZIP=%REACT_ZIP%
pushd frontend\dist || exit /b 11
tar.exe -a -c -f "%REACT_ZIP%" *
echo TAR_EXIT:%ERRORLEVEL%
popd
if exist "%REACT_ZIP%" (
  echo ZIP_OK
  dir "%ARTIFACT_DIR%"
  exit /b 0
)
echo ZIP_MISSING
exit /b 12
