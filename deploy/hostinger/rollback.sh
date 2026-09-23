#!/usr/bin/env bash
set -euo pipefail
domain_root=$(realpath -e "${1:?Domain root required}")
private="$domain_root/.mds"
[[ "$domain_root" != / && -L "$private/previous" ]] || { echo 'No previous release.' >&2; exit 1; }
exec 9>"$private/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
previous=$(readlink -f "$private/previous")
case "$previous" in "$private/releases/"*) ;; *) echo 'Invalid previous target.' >&2; exit 1 ;; esac
[[ -f "$previous/public/index.php" ]] || exit 1
ln -s "$previous" "$private/current.rollback"
mv -Tf "$private/current.rollback" "$private/current"
echo 'Previous application restored. Database has NOT been rolled back; migrations must remain backward compatible.'
