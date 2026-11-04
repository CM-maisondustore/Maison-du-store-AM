param([string]$Php, [string]$ComposerPhar, [string]$NodeDirectory)
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path $PSScriptRoot -Parent
if (!$Php) {
    $installed = Get-Command php -ErrorAction SilentlyContinue
    $Php = if ($installed) { $installed.Source } else { Join-Path $env:TEMP 'mds-backend-tools\php\php.exe' }
}
if (!$ComposerPhar) { $ComposerPhar = Join-Path $env:TEMP 'mds-backend-tools\composer.phar' }
if (!$NodeDirectory -and !(Get-Command npm.cmd -ErrorAction SilentlyContinue)) {
    $NodeDirectory = Get-ChildItem (Join-Path $env:TEMP 'mds-backend-tools\node') -Directory | Select-Object -First 1 -ExpandProperty FullName
}
if ($NodeDirectory) { $env:PATH = "$NodeDirectory;" + $env:PATH }
function Check-Exit { if ($LASTEXITCODE -ne 0) { throw "Command failed ($LASTEXITCODE). No release published." } }
Push-Location $repoRoot
try {
    $dirty = git status --porcelain
    Check-Exit
    if ($dirty) { throw 'Commit the source changes before building a release.' }
    $revision = git rev-parse HEAD
    Check-Exit
    $output = Join-Path $repoRoot ('artifacts/' + $revision + '-' + (Get-Date -Format 'yyyyMMddHHmmss'))
    New-Item -ItemType Directory -Path $output | Out-Null
    Push-Location Backend
    try {
        & $Php vendor/bin/phpunit
        Check-Exit
        npm.cmd ci --no-fund
        Check-Exit
        npm.cmd test
        Check-Exit
        npm.cmd run build
        Check-Exit
    } finally { Pop-Location }
    # Même liste que la CI : deploy/release-files.txt.
    $archivePaths = @(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'release-files.txt') | ForEach-Object { $_.Trim() } | Where-Object { $_ -and !$_.StartsWith('#') } | ForEach-Object { 'Backend/' + $_ })
    git archive --format=tar -o "$output/source.tar" HEAD @archivePaths
    Check-Exit
    tar -xf "$output/source.tar" -C $output
    Check-Exit
    $package = Join-Path $output Backend
    Copy-Item -LiteralPath (Join-Path $repoRoot 'Backend/public/build') -Destination (Join-Path $package 'public/build') -Recurse
    # Composer discovery needs runtime directories; they are omitted from the final archive.
    foreach ($directory in @('storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $package $directory) | Out-Null
    }
    Push-Location $package
    try {
        if (Test-Path -LiteralPath $ComposerPhar) {
            & $Php $ComposerPhar install --no-dev --prefer-dist --no-interaction --optimize-autoloader
        } else { composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader }
        Check-Exit
        [IO.File]::WriteAllText((Join-Path $package 'REVISION'), "$revision`n", [Text.UTF8Encoding]::new($false))
        tar --exclude=./storage --exclude=./bootstrap/cache/*.php -czf "$output/hostinger-release.tar.gz" .
        Check-Exit
    } finally { Pop-Location }
    $hash = (Get-FileHash "$output/hostinger-release.tar.gz" -Algorithm SHA256).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText("$output/hostinger-release.tar.gz.sha256", "$hash  hostinger-release.tar.gz`n", [Text.UTF8Encoding]::new($false))
    # Même disposition que l'artefact CI : publish.ps1 envoie les scripts de cette révision, pas ceux de la copie locale.
    git archive --format=tar -o "$output/scripts.tar" HEAD deploy/hostinger/release.sh deploy/hostinger/rollback.sh deploy/hostinger/backup.sh deploy/hostinger/backup-options.php
    Check-Exit
    tar -xf "$output/scripts.tar" -C $output
    Check-Exit
    Write-Output "Release ready: $output/hostinger-release.tar.gz"
} finally { Pop-Location }
