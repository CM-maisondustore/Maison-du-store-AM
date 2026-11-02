param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpExecutable = if ($phpCommand) { $phpCommand.Source } else { Join-Path $env:TEMP 'mds-backend-tools\php\php.exe' }
if (!(Test-Path -LiteralPath $phpExecutable)) { throw 'Installez PHP 8.2+ et ajoutez-le au PATH (voir README.md).' }
Push-Location $PSScriptRoot
try {
    & $phpExecutable artisan serve --no-reload --host=127.0.0.1 --port=$Port
} finally {
    Pop-Location
}
