@echo off
setlocal EnableExtensions
cd /d "%~dp0"
echo Current directory: %cd%
if exist react_tmp_test.zip del /f /q react_tmp_test.zip
if not exist frontend\dist (
  echo ERROR: frontend\dist not found
  exit /b 2
)
pushd frontend\dist
tar.exe -a -c -f "%~dp0react_tmp_test.zip" *
echo TAR_EXIT:%ERRORLEVEL%
popd
if not exist react_tmp_test.zip (
  echo ERROR: react_tmp_test.zip was not created
  exit /b 3
)
echo SUCCESS
exit /b 0
