param(
    [Parameter(Mandatory = $true)]
    [string]$RootDir,

    [Parameter(Mandatory = $true)]
    [string]$DestinationZip
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$resolvedRootDir = (Resolve-Path -LiteralPath $RootDir).Path
$destinationDir = Split-Path -Parent $DestinationZip

if (-not (Test-Path -LiteralPath $destinationDir)) {
    New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
}

$paths = @(
    'admin',
    'api',
    'assets',
    'backend',
    'components',
    'includes',
    'lib',
    'uploads',
    'alwaysdata.env',
    'alwaysdata.htaccess'
)

# Exclude dev/test/debug/migration PHP files that should never go to production
$excludedPhpFiles = @(
    'test.php', 'test_cloud.php', 'test_ftp_connection.php', 'test-redirect.php',
    'debug_pics.php', 'unzip_debug.php', 'unzip_react_debug.php',
    'verify_alignment_fixes.php', 'git_index_head.php', 'index_tmp.php',
    'migrate.php', 'setup.php', 'patch_pics.php'
)

$paths += Get-ChildItem -LiteralPath $resolvedRootDir -Filter '*.php' -File |
    Where-Object { $excludedPhpFiles -notcontains $_.Name } |
    Select-Object -ExpandProperty Name
$existing = $paths | Where-Object { Test-Path -LiteralPath (Join-Path $resolvedRootDir $_) }

if (-not $existing -or $existing.Count -eq 0) {
    throw "No PHP deployment files found under $resolvedRootDir"
}

$stagingDir = Join-Path ([System.IO.Path]::GetTempPath()) ("marketplace-app-stage-" + [System.Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null

try {
    foreach ($relativePath in $existing) {
        $sourcePath = Join-Path $resolvedRootDir $relativePath
        $targetPath = Join-Path $stagingDir $relativePath
        $targetParent = Split-Path -Parent $targetPath

        if (-not (Test-Path -LiteralPath $targetParent)) {
            New-Item -ItemType Directory -Path $targetParent -Force | Out-Null
        }

        Copy-Item -LiteralPath $sourcePath -Destination $targetPath -Recurse -Force
    }

    if (Test-Path -LiteralPath $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }

    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $stagingDir,
        $DestinationZip,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )
}
finally {
    if (Test-Path -LiteralPath $stagingDir) {
        Remove-Item -LiteralPath $stagingDir -Recurse -Force
    }
}
