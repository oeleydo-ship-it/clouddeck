# Build a CodeCanyon-ready zip of Uplary (source only — buyers run composer/npm on the server).
param(
    [string]$Version = "1.0.0"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Stamp = Get-Date -Format "yyyyMMdd"
$OutDir = Join-Path $Root "dist"
$Archive = Join-Path $OutDir "uplary-$Version-$Stamp.zip"
$Staging = Join-Path $OutDir ".staging-uplary"
$Target = Join-Path $Staging "uplary"

if (Test-Path $Staging) { Remove-Item -Recurse -Force $Staging }
New-Item -ItemType Directory -Force -Path $Target, $OutDir | Out-Null

$ExcludeDirs = @(
    '.git', '.github', '.cursor', '.claude', 'node_modules', 'vendor', 'dist',
    '.phpunit.cache', 'coverage'
)

$ExcludeFiles = @(
    '.env', 'public\hot', '.phpunit.result.cache'
)

Write-Host "Copying application files..."
Get-ChildItem -Path $Root -Force | Where-Object {
    $_.Name -notin $ExcludeDirs -and $_.Name -notlike '*.zip'
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination (Join-Path $Target $_.Name) -Recurse -Force
}

foreach ($dir in $ExcludeDirs) {
    $path = Join-Path $Target $dir
    if (Test-Path $path) { Remove-Item -Recurse -Force $path }
}

foreach ($file in $ExcludeFiles) {
    $path = Join-Path $Target $file
    if (Test-Path $path) { Remove-Item -Force $path }
}

# Clean storage runtime files but keep structure
@(
    'storage\logs',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\app\private',
    'storage\app\public',
    'bootstrap\cache',
    'public\build'
) | ForEach-Object {
    $path = Join-Path $Target $_
    if (Test-Path $path) { Remove-Item -Recurse -Force $path }
    New-Item -ItemType Directory -Force -Path $path | Out-Null
}

New-Item -ItemType File -Force -Path (Join-Path $Target 'storage\logs\.gitkeep') | Out-Null
New-Item -ItemType File -Force -Path (Join-Path $Target 'storage\app\public\.gitkeep') | Out-Null
Set-Content -Path (Join-Path $Target 'VERSION') -Value $Version -NoNewline

if (Test-Path $Archive) { Remove-Item -Force $Archive }
Compress-Archive -Path $Target -DestinationPath $Archive -CompressionLevel Optimal
Remove-Item -Recurse -Force $Staging

$Bytes = (Get-Item $Archive).Length
Write-Host "Done: $Archive ($Bytes bytes)"
Write-Host ""
Write-Host "Buyers should:"
Write-Host "  1. Unzip and point the web root to uplary/public"
Write-Host "  2. cp .env.example .env && php artisan key:generate"
Write-Host "  3. composer install --no-dev && npm ci && npm run build"
Write-Host "  4. php artisan migrate && visit /install"
Write-Host "  See INSTALLATION.md for production Horizon, Redis, and Reverb."
