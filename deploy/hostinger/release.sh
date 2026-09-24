#!/usr/bin/env bash
set -euo pipefail
umask 027
# Usage: bash release.sh /absolute/domain/root /absolute/release.tar.gz <git-sha>
domain_root=$(realpath -e "${1:?Domain root required}")
archive=$(realpath -e "${2:?Release archive required}")
revision=${3:?Git revision required}
[[ "$domain_root" != / && "$revision" =~ ^[a-f0-9]{40}$ ]] || { echo 'Invalid root or revision' >&2; exit 1; }
php_bin=${PHP_BIN:-php}
private="$domain_root/.mds"
shared="$private/shared"
release="$private/releases/$revision"
public="$domain_root/public_html"
mkdir -p "$private/releases" "$shared/storage/framework/cache/data" "$shared/storage/framework/sessions" "$shared/storage/framework/views" "$shared/storage/logs"
# Existing websites must be reviewed and backed up before the first switch.
if [[ -e "$public" || -L "$public" ]]; then
    [[ -L "$public" && "$(readlink "$public")" == "$private/current/public" ]] || { echo 'public_html is not managed by MDS. Back up and relocate it before first deployment.' >&2; exit 1; }
fi
[[ -f "$shared/.env" ]] || { echo 'Create the private shared/.env using the environment template first.' >&2; exit 1; }
[[ ! -e "$release" ]] || { echo 'This revision is already extracted. Use rollback.sh or a new commit.' >&2; exit 1; }
exec 9>"$private/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
# Back up an existing installation before applying any new migration. Shell
# mysqldump works on Hostinger even when PHP proc_open is disabled.
if [[ -L "$private/current" ]]; then
    bash "$(dirname "$0")/backup.sh" "$domain_root"
fi
mkdir "$release"
tar -xzf "$archive" -C "$release"
# Extraction may restore a 750 mode on the release root. Hostinger's web
# server needs traversal to public/, while shared configuration stays private.
chmod 755 "$private" "$private/releases" "$release"
chmod 700 "$shared"
find "$release/public" -type d -exec chmod 755 {} +
find "$release/public" -type f -exec chmod 644 {} +
[[ "$(cat "$release/REVISION")" == "$revision" ]] || { echo 'Archive revision mismatch.' >&2; exit 1; }
[[ -f "$release/public/build/manifest.json" && -f "$release/vendor/autoload.php" && ! -e "$release/.env" ]] || { echo 'Incomplete or unsafe release.' >&2; exit 1; }
ln -s "$shared/.env" "$release/.env"
# Package deliberately omits storage; all releases share sessions and logs.
ln -s "$shared/storage" "$release/storage"
chmod 600 "$shared/.env"
cd "$release"
"$php_bin" vendor/composer/platform_check.php
"$php_bin" artisan config:cache
chmod 600 bootstrap/cache/config.php
"$php_bin" deployment-check.php
"$php_bin" artisan migrate --force
"$php_bin" artisan db:seed --force
"$php_bin" artisan route:cache
"$php_bin" artisan view:cache
# Readiness includes the expected schema, not just a live PHP process.
"$php_bin" deployment-check.php --database
old_release=$(readlink "$private/current" || true)
if [[ -n "$old_release" ]]; then
    ln -s "$old_release" "$private/previous.next"
    mv -Tf "$private/previous.next" "$private/previous"
fi
ln -s "$release" "$private/current.next"
mv -Tf "$private/current.next" "$private/current"
if [[ ! -L "$public" ]]; then ln -s "$private/current/public" "$public"; fi
echo "Activated $revision. Verify HTTPS, /health/ready and forms. Previous release retained."
