param(
    [Parameter(Mandatory = $true)]
    [string]$SourceDir,

    [Parameter(Mandatory = $true)]
    [string]$DestinationZip
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$resolvedSourceDir = (Resolve-Path -LiteralPath $SourceDir).Path
$destinationDir = Split-Path -Parent $DestinationZip

if (-not (Test-Path -LiteralPath $resolvedSourceDir -PathType Container)) {
    throw "React build directory not found: $SourceDir"
}

if (-not (Test-Path -LiteralPath $destinationDir)) {
    New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
}

$items = Get-ChildItem -LiteralPath $resolvedSourceDir -Force
if (-not $items -or $items.Count -eq 0) {
    throw "React build directory is empty: $resolvedSourceDir"
}

if (Test-Path -LiteralPath $DestinationZip) {
    Remove-Item -LiteralPath $DestinationZip -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($DestinationZip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $allFiles = Get-ChildItem -LiteralPath $resolvedSourceDir -Recurse -File
    foreach ($file in $allFiles) {
        $entryName = $file.FullName.Substring($resolvedSourceDir.Length).TrimStart('\', '/').Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $file.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $zip.Dispose()
}

