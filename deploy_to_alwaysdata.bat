@echo off
set /p ad_pass="Enter your AlwaysData password: "

echo Packaging the full PHP application (app.zip)...
if exist app.zip del app.zip
tar.exe -a -c -f app.zip admin api assets backend components includes lib uploads *.php *.env alwaysdata.htaccess

echo Creating unzip script...
echo ^<?php $zip = new ZipArchive; if ($zip-^>open('app.zip') === TRUE) { $zip-^>extractTo('./'); $zip-^>close(); if(file_exists('alwaysdata.env')) { rename('alwaysdata.env', '.env'); } if(file_exists('alwaysdata.htaccess')) { rename('alwaysdata.htaccess', '.htaccess'); } echo 'SUCCESS'; } else { echo 'FAILED'; } ?^> > unzip.php

echo Uploading app.zip...
curl.exe --ssl-reqd -T app.zip ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass%

echo Uploading unzip.php...
curl.exe --ssl-reqd -T unzip.php ftp://ftp-campusmarketplace.alwaysdata.net/www/ -u campusmarketplace:%ad_pass%

echo Fixing server configuration (.htaccess)...
curl.exe --ssl-reqd -T alwaysdata.htaccess ftp://ftp-campusmarketplace.alwaysdata.net/www/.htaccess -u campusmarketplace:%ad_pass%

echo Extracting files on the server...
curl.exe -s https://campusmarketplace.alwaysdata.net/unzip.php

echo Cleaning up local temp files...
del app.zip
del unzip.php

echo.
echo Deployment finished!
pause
