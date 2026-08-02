#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
LABEL={{LABEL}}
ROOT="/var/www/${DOMAIN}"
SHARED="${ROOT}/shared"
ARCHIVE="${SHARED}/backups/${LABEL}"

[ -f "${ARCHIVE}/database.sql.gz" ] || { echo "Backup ${LABEL} is missing its database dump" >&2; exit 1; }
[ -f "${ARCHIVE}/wp-content.tar.gz" ] || { echo "Backup ${LABEL} is missing its content archive" >&2; exit 1; }

# The current state is captured first: a restore that turns out to be the wrong one would
# otherwise be unrecoverable.
SAFETY="${SHARED}/backups/pre-restore-$(date +%Y%m%d%H%M%S)"
mkdir -p "${SAFETY}"
sudo -u www-data /usr/local/bin/wp --path="${ROOT}/current" --no-color db export "${SAFETY}/database.sql" --add-drop-table
gzip -f "${SAFETY}/database.sql"
tar czf "${SAFETY}/wp-content.tar.gz" -C "${SHARED}" wp-content

gunzip -c "${ARCHIVE}/database.sql.gz" > /tmp/clouddeck-restore.sql
sudo -u www-data /usr/local/bin/wp --path="${ROOT}/current" --no-color db import /tmp/clouddeck-restore.sql
rm -f /tmp/clouddeck-restore.sql

rm -rf "${SHARED}/wp-content.restoring"
mkdir -p "${SHARED}/wp-content.restoring"
tar xzf "${ARCHIVE}/wp-content.tar.gz" -C "${SHARED}/wp-content.restoring" --strip-components=1
rm -rf "${SHARED}/wp-content.previous"
mv "${SHARED}/wp-content" "${SHARED}/wp-content.previous"
mv "${SHARED}/wp-content.restoring" "${SHARED}/wp-content"
rm -rf "${SHARED}/wp-content.previous"

chown -R www-data:www-data "${SHARED}/wp-content"
systemctl reload nginx
echo "Restored ${LABEL}"
