$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$ftpBase = 'ftp://ftp-campusmarketplace.alwaysdata.net/www'
$httpBase = 'https://campusmarketplace.alwaysdata.net'
$username = 'campusmarketplace'

$files = @(
    'includes/google_auth.php',
    'google_signin.php',
    'google_account_choice.php',
    'login.php'
)

$plainPassword = $env:ALWAYSDATA_PASSWORD
if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    $securePassword = Read-Host 'Enter your AlwaysData password' -AsSecureString
    $plainPassword = [System.Net.NetworkCredential]::new('', $securePassword).Password
}

if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    throw 'AlwaysData password was not provided.'
}

$credential = "$username`:$plainPassword"

foreach ($relativePath in $files) {
    $localPath = Join-Path $repoRoot $relativePath
    if (-not (Test-Path $localPath)) {
        throw "Missing file: $relativePath"
    }

    $normalizedRemotePath = ($relativePath -replace '\\', '/')
    $remoteTempName = '.tmp_' + [guid]::NewGuid().ToString('N') + '_' + [System.IO.Path]::GetFileName($normalizedRemotePath)
    $remoteTempUrl = $ftpBase.TrimEnd('/') + '/' + $remoteTempName

    Write-Host "Uploading $relativePath ..." -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd -T $localPath $remoteTempUrl -u $credential
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $relativePath"
    }

    & curl.exe --noproxy "*" --ssl-reqd "ftp://ftp-campusmarketplace.alwaysdata.net/" -Q "RNFR /www/$remoteTempName" -Q "RNTO /www/$normalizedRemotePath" -u $credential
    if ($LASTEXITCODE -ne 0) {
        throw "Rename failed for $relativePath"
    }
}

$resetFileName = 'opcache_reset_' + [guid]::NewGuid().ToString('N') + '.php'
$tempResetPath = Join-Path $env:TEMP $resetFileName
$resetPhp = "<?php if (function_exists('opcache_reset')) { @opcache_reset(); } echo 'OK';"
[System.IO.File]::WriteAllText($tempResetPath, $resetPhp, [System.Text.Encoding]::UTF8)

try {
    $remoteResetUrl = $ftpBase.TrimEnd('/') + '/' + $resetFileName
    Write-Host "Uploading $resetFileName ..." -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd -T $tempResetPath $remoteResetUrl -u $credential
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $resetFileName"
    }

    Write-Host 'Resetting PHP opcode cache ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" "$httpBase/$resetFileName" --max-time 20
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not call remote opcache reset endpoint.'
    }

    Write-Host 'Cleaning up remote reset script ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd "ftp://ftp-campusmarketplace.alwaysdata.net/" -Q "DELE /www/$resetFileName" -u $credential
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Could not delete remote reset script $resetFileName. Please remove it manually if it remains."
    }
}
finally {
    if (Test-Path $tempResetPath) {
        Remove-Item -LiteralPath $tempResetPath -Force -ErrorAction SilentlyContinue
    }
}

Write-Host 'Google account choice deployment completed.' -ForegroundColor Green
