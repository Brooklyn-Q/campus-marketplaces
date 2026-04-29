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

$paths += Get-ChildItem -LiteralPath $resolvedRootDir -Filter '*.php' -File | Select-Object -ExpandProperty Name
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
