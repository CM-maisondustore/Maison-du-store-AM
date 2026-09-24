#!/usr/bin/env bash
set -euo pipefail
umask 077
root=$(realpath -e "${1:?Domain root required}")
[[ "$root" != / ]] || exit 1
private="$root/.mds"
[[ -L "$private/current" && -f "$private/current/artisan" ]] || { echo 'No installed application to back up'; exit 1; }
helper="$(cd "$(dirname "$0")" && pwd)/backup-options.php"
stamp=$(date -u +%Y%m%dT%H%M%SZ)-$$
mkdir -p "$private/backups"
chmod 700 "$private/backups"
options="$private/backups/mysql-options-$stamp.cnf"
namefile="$private/backups/mysql-name-$stamp.txt"
destination="$private/backups/database-$stamp.sql"
trap 'rm -f -- "$options" "$namefile"' EXIT
"${PHP_BIN:-php}" "$helper" "$private/current" "$options" "$namefile"
[[ -s "$options" && -s "$namefile" ]] || { echo 'Backup configuration missing'; exit 1; }
database=$(cat "$namefile")
mysqldump --defaults-extra-file="$options" --single-transaction --skip-lock-tables --no-tablespaces "$database" > "$destination.pending"
[[ -s "$destination.pending" ]] || { echo 'Empty database dump'; exit 1; }
mv -- "$destination.pending" "$destination"
cp "$private/shared/.env" "$private/backups/configuration-$stamp.env"
chmod 600 "$destination" "$private/backups/configuration-$stamp.env"
echo 'Private MySQL and environment backups completed.'
