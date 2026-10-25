#!/usr/bin/env bash
set -euo pipefail
umask 077
# KEEP_BACKUPS (10 par défaut) : nombre de jeux de sauvegarde conservés (base, configuration, fichiers).
root=$(realpath -e "${1:?Domain root required}")
[[ "$root" != / ]] || exit 1
keep_backups=${KEEP_BACKUPS:-10}
[[ "$keep_backups" =~ ^[1-9][0-9]*$ ]] || { echo 'KEEP_BACKUPS must be a positive integer.' >&2; exit 1; }
private="$root/.mds"
[[ -L "$private/current" && -f "$private/current/artisan" ]] || { echo 'No installed application to back up'; exit 1; }
helper="$(cd "$(dirname "$0")" && pwd)/backup-options.php"
stamp=$(date -u +%Y%m%dT%H%M%SZ)-$$
backups="$private/backups"
mkdir -p "$backups"
chmod 700 "$backups"
options="$backups/mysql-options-$stamp.cnf"
namefile="$backups/mysql-name-$stamp.txt"
destination="$backups/database-$stamp.sql.gz"
trap 'rm -f -- "$options" "$namefile" "$destination.pending" "$backups/assets-$stamp.tar.gz.pending"' EXIT
"${PHP_BIN:-php}" "$helper" "$private/current" "$options" "$namefile"
[[ -s "$options" && -s "$namefile" ]] || { echo 'Backup configuration missing'; exit 1; }
database=$(cat "$namefile")
mysqldump --defaults-extra-file="$options" --single-transaction --skip-lock-tables --no-tablespaces "$database" | gzip > "$destination.pending"
# mysqldump n'écrit cette ligne qu'à la fin d'un export complet : un dump tronqué est refusé.
gzip -t "$destination.pending"
gzip -dc "$destination.pending" | tail -n 1 | grep -q '^-- Dump completed' || { echo 'Incomplete database dump'; exit 1; }
mv -- "$destination.pending" "$destination"
cp "$private/shared/.env" "$backups/configuration-$stamp.env"
if [[ -d "$private/shared/storage/app" ]]; then
    tar -czf "$backups/assets-$stamp.tar.gz.pending" -C "$private/shared/storage" app
    tar -tzf "$backups/assets-$stamp.tar.gz.pending" > /dev/null
    mv -- "$backups/assets-$stamp.tar.gz.pending" "$backups/assets-$stamp.tar.gz"
    chmod 600 "$backups/assets-$stamp.tar.gz"
fi
chmod 600 "$destination" "$backups/configuration-$stamp.env"
# Rotation par jeu complet, du plus récent au plus ancien ; les autres fichiers de backups/ ne sont pas touchés.
find "$backups" -maxdepth 1 -type f -regextype posix-extended -regex '.*/database-[0-9]{8}T[0-9]{6}Z-[0-9]+\.sql(\.gz)?' -printf '%f\n' \
    | sed -E 's/^database-(.*)\.sql(\.gz)?$/\1/' | sort -ru | tail -n +$((keep_backups + 1)) | while read -r old; do
    rm -f -- "$backups/database-$old.sql" "$backups/database-$old.sql.gz" "$backups/configuration-$old.env" "$backups/assets-$old.tar.gz"
done
echo "Private MySQL (including PDF archives), environment and storage assets backups completed: database-$stamp.sql.gz"
