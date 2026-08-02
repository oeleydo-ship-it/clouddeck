#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
LABEL={{LABEL}}
ROOT="/var/www/${DOMAIN}"
SHARED="${ROOT}/shared"
BACKUPS="${SHARED}/backups"
ARCHIVE="${BACKUPS}/${LABEL}"

mkdir -p "${ARCHIVE}"

# The database and wp-content together are the whole site: core files come back from
# wordpress.org on any deployment, but these two cannot be recreated.
if [ ! -x /usr/local/bin/wp ]; then
    curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /usr/local/bin/wp
fi

sudo -u www-data /usr/local/bin/wp --path="${ROOT}/current" --no-color db export "${ARCHIVE}/database.sql" --add-drop-table
tar czf "${ARCHIVE}/wp-content.tar.gz" -C "${SHARED}" wp-content
gzip -f "${ARCHIVE}/database.sql"

echo "CLOUDDECK_BACKUP_BYTES=$(du -sb "${ARCHIVE}" | cut -f1)"

# Keeping every backup would fill the disk and take the site down with it.
cd "${BACKUPS}"
ls -1dt */ 2>/dev/null | tail -n +11 | xargs -r rm -rf
echo "Backup ${LABEL} complete"
