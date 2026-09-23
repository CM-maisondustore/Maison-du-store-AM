param(
    [Parameter(Mandatory)][string]$SshHost,
    [Parameter(Mandatory)][string]$SshUser,
    [Parameter(Mandatory)][string]$DomainRoot,
    [Parameter(Mandatory)][string]$Archive,
    [int]$Port = 65002
)
$ErrorActionPreference = 'Stop'
if ($SshHost -notmatch '^[a-zA-Z0-9.-]+$' -or $SshUser -notmatch '^[a-zA-Z0-9_-]+$' -or $DomainRoot -notmatch '^/home/[a-zA-Z0-9_./-]+$' -or $DomainRoot.Contains('..') -or $Port -lt 1 -or $Port -gt 65535) { throw 'Invalid SSH parameters.' }
$Archive = (Resolve-Path -LiteralPath $Archive).Path
$revisionMember = tar -tf $Archive | Where-Object { $_ -match '^(\./)?REVISION$' } | Select-Object -First 1
if (!$revisionMember) { throw 'Archive has no revision metadata.' }
$revision = (tar -xOf $Archive $revisionMember).Trim()
if ($LASTEXITCODE -ne 0 -or $revision -notmatch '^[a-f0-9]{40}$') { throw 'Invalid release archive.' }
$expectedHash = ((Get-Content -LiteralPath "$Archive.sha256" -Raw).Trim() -split '\s+')[0]
if ((Get-FileHash -LiteralPath $Archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash) { throw 'Archive checksum mismatch.' }
$target = "$SshUser@$SshHost"
$incoming = "$DomainRoot/.mds/incoming"
$sshOptions = @('-p', "$Port", '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes')
& ssh @sshOptions $target "mkdir -p '$incoming'"
if ($LASTEXITCODE -ne 0) { throw 'SSH failed. Configure the SSH key and verify the Hostinger host fingerprint first.' }
& scp -P $Port -o BatchMode=yes -o StrictHostKeyChecking=yes $Archive "$Archive.sha256" "$PSScriptRoot/release.sh" "${target}:$incoming/"
if ($LASTEXITCODE -ne 0) { throw 'Upload failed.' }
& ssh @sshOptions $target "cd '$incoming' && sha256sum -c hostinger-release.tar.gz.sha256 && bash release.sh '$DomainRoot' '$incoming/hostinger-release.tar.gz' '$revision'"
if ($LASTEXITCODE -ne 0) { throw 'Remote release failed. Read the reported error; the script preserves existing releases.' }
