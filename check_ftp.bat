@echo off
set /p ad_pass="Enter your AlwaysData password: "
echo.
echo Listing directories on your server...
curl.exe -l ftp://ftp-campusmarketplace.alwaysdata.net/ -u campusmarketplace:%ad_pass%
echo.
pause
