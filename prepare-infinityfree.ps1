param(
    [string]$AppFolderName = 'laravel_accounting',
    [string]$OutputDir = '.deployment/infinityfree',
    [switch]$RunComposer,
    [switch]$BuildAssets
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$outputRoot = Join-Path $projectRoot $OutputDir
$appOutput = Join-Path $outputRoot $AppFolderName
$htdocsOutput = Join-Path $outputRoot 'htdocs'

function Remove-IfExists {
    param([string]$PathToRemove)

    if (Test-Path $PathToRemove) {
        Remove-Item -Recurse -Force $PathToRemove
    }
}

function Copy-DirectoryContent {
    param(
        [string]$Source,
        [string]$Destination,
        [string[]]$ExcludeTopLevel
    )

    New-Item -ItemType Directory -Force -Path $Destination | Out-Null

    Get-ChildItem -LiteralPath $Source -Force | Where-Object {
        $ExcludeTopLevel -notcontains $_.Name
    } | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $Destination -Recurse -Force
    }
}

Set-Location $projectRoot

if ($RunComposer) {
    Write-Host 'Installing production Composer dependencies...'
    composer install --no-dev --optimize-autoloader
    if ($LASTEXITCODE -ne 0) {
        throw 'Composer install failed.'
    }
}

if ($BuildAssets) {
    Write-Host 'Building frontend assets...'
    npm install
    if ($LASTEXITCODE -ne 0) {
        throw 'npm install failed.'
    }

    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw 'npm run build failed.'
    }
}

Write-Host 'Removing generated cache files from the workspace copy...'

$generatedCacheFiles = @(
    (Join-Path $projectRoot 'bootstrap/cache/packages.php'),
    (Join-Path $projectRoot 'bootstrap/cache/services.php'),
    (Join-Path $projectRoot 'bootstrap/cache/config.php')
)

foreach ($cacheFile in $generatedCacheFiles) {
    if (Test-Path $cacheFile) {
        Remove-Item -Force $cacheFile
    }
}

Write-Host 'Preparing InfinityFree upload bundle...'
Remove-IfExists $outputRoot
New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null

Copy-DirectoryContent -Source (Join-Path $projectRoot 'infinityfree/htdocs') -Destination $htdocsOutput -ExcludeTopLevel @()
Copy-DirectoryContent -Source $projectRoot -Destination $appOutput -ExcludeTopLevel @(
    '.git',
    '.github',
    '.deployment',
    'node_modules',
    'infinityfree'
)

$sqliteFile = Join-Path $appOutput 'database/database.sqlite'
if (Test-Path $sqliteFile) {
    Remove-Item -Force $sqliteFile
}

$renderCheckFile = Join-Path $appOutput 'storage/app/render-check.sqlite'
if (Test-Path $renderCheckFile) {
    Remove-Item -Force $renderCheckFile
}

Write-Host ''
Write-Host 'InfinityFree bundle ready:'
Write-Host "- htdocs files: $htdocsOutput"
Write-Host "- app files:    $appOutput"
Write-Host ''
Write-Host 'Upload htdocs contents into InfinityFree htdocs/'
Write-Host "Upload app folder beside htdocs/ using the name: $AppFolderName"
