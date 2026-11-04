param(
    [Parameter(Mandatory)][string]$SshHost,
    [Parameter(Mandatory)][string]$SshUser,
    [Parameter(Mandatory)][string]$DomainRoot,
    [Parameter(Mandatory)][string]$Archive,
    [int]$Port = 65002,
    [string]$IdentityFile,
    [string]$KnownHostsFile
)
$ErrorActionPreference = 'Stop'
if ($SshHost -notmatch '^[a-zA-Z0-9.-]+$' -or $SshUser -notmatch '^[a-zA-Z0-9_-]+$' -or $DomainRoot -notmatch '^/home/[a-zA-Z0-9_./-]+$' -or $DomainRoot.Contains('..') -or $Port -lt 1 -or $Port -gt 65535) { throw 'Invalid SSH parameters.' }
$Archive = (Resolve-Path -LiteralPath $Archive).Path
if ((Split-Path -Leaf $Archive) -ne 'hostinger-release.tar.gz') { throw 'The archive must keep its name hostinger-release.tar.gz (checksum file reference).' }
# Scripts livrés avec l'archive (artefact CI ou build-release.ps1), jamais ceux de la copie locale.
$scriptsDirectory = Join-Path (Split-Path -Parent $Archive) 'deploy/hostinger'
$scriptNames = @('release.sh', 'rollback.sh', 'backup.sh', 'backup-options.php')
$scriptPaths = @($scriptNames | ForEach-Object { Join-Path $scriptsDirectory $_ })
foreach ($script in $scriptPaths) { if (!(Test-Path -LiteralPath $script -PathType Leaf)) { throw "Missing $script next to the archive. Use the complete CI artifact or build-release.ps1 output." } }
$members = tar -tf $Archive
if ($LASTEXITCODE -ne 0) { throw 'Unreadable release archive.' }
$revisionMember = $members | Where-Object { $_ -match '^(\./)?REVISION$' } | Select-Object -First 1
if (!$revisionMember) { throw 'Archive has no revision metadata.' }
$revision = (tar -xOf $Archive $revisionMember).Trim()
if ($LASTEXITCODE -ne 0 -or $revision -notmatch '^[a-f0-9]{40}$') { throw 'Invalid release archive.' }
$expectedHash = ((Get-Content -LiteralPath "$Archive.sha256" -Raw).Trim() -split '\s+')[0]
if ((Get-FileHash -LiteralPath $Archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash) { throw 'Archive checksum mismatch.' }
$target = "$SshUser@$SshHost"
$incoming = "$DomainRoot/.mds/incoming"
$sshOptions = @('-p', "$Port", '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes')
$scpOptions = @('-P', "$Port", '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes')
$defaultIdentity = Join-Path $env:USERPROFILE '.ssh/mds_hostinger'
$defaultKnownHosts = Join-Path $PSScriptRoot '../../artifacts/ssh/known_hosts'
if (!$IdentityFile -and (Test-Path -LiteralPath $defaultIdentity)) { $IdentityFile = $defaultIdentity }
if (!$KnownHostsFile -and (Test-Path -LiteralPath $defaultKnownHosts)) { $KnownHostsFile = $defaultKnownHosts }
if ($IdentityFile) {
    $identityOptions = @('-i', (Resolve-Path -LiteralPath $IdentityFile).Path, '-o', 'IdentitiesOnly=yes')
    $sshOptions += $identityOptions
    $scpOptions += $identityOptions
}
if ($KnownHostsFile) {
    $hostOptions = @('-o', ('UserKnownHostsFile=' + (Resolve-Path -LiteralPath $KnownHostsFile).Path))
    $sshOptions += $hostOptions
    $scpOptions += $hostOptions
}
& ssh @sshOptions $target "mkdir -p '$incoming'"
if ($LASTEXITCODE -ne 0) { throw 'SSH failed. Configure the SSH key and verify the Hostinger host fingerprint first.' }
& scp @scpOptions $Archive "$Archive.sha256" @scriptPaths "${target}:$incoming/"
if ($LASTEXITCODE -ne 0) { throw 'Upload failed.' }
& ssh @sshOptions $target "cd '$incoming' && sha256sum -c hostinger-release.tar.gz.sha256 && bash release.sh '$DomainRoot' '$incoming/hostinger-release.tar.gz' '$revision'"
if ($LASTEXITCODE -ne 0) { throw 'Remote release failed. Read the reported error; the script preserves existing releases.' }
