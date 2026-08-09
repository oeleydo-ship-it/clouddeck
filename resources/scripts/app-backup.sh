#!/usr/bin/env bash
set -Eeuo pipefail

# Full application backup: live code (current + shared) + database dump.
# Archive is written under /tmp for the control plane to stream off the VPS.

DOMAIN={{DOMAIN}}
BACKUP_ID={{BACKUP_ID}}
PLATFORM={{PLATFORM}}
DB_ENGINE={{DB_ENGINE}}
DB_NAME={{DB_NAME}}

ROOT="/var/www/${DOMAIN}"
STAGING="/tmp/uplary-site-backup-${BACKUP_ID}"
ARCHIVE="/tmp/uplary-site-backup-${BACKUP_ID}.tar.gz"

rm -rf "${STAGING}" "${ARCHIVE}"
mkdir -p "${STAGING}/files"

if [ ! -d "${ROOT}/current" ] || [ ! -d "${ROOT}/shared" ]; then
    echo "Site root is missing current/ or shared/ under ${ROOT}" >&2
    exit 1
fi

# Resolve the live release; do not pack every historical release.
CURRENT_TARGET="$(readlink -f "${ROOT}/current")"
mkdir -p "${STAGING}/files/current"
# Prefer hard-linking when possible to speed large trees; fall back to copy.
cp -a "${CURRENT_TARGET}/." "${STAGING}/files/current/" 2>/dev/null || cp -a "${CURRENT_TARGET}/." "${STAGING}/files/current/"

mkdir -p "${STAGING}/files/shared"
# Exclude on-server WordPress backup dirs and other transient noise.
if command -v rsync >/dev/null 2>&1; then
    rsync -a \
        --exclude 'backups/' \
        --exclude 'node_modules/' \
        "${ROOT}/shared/" "${STAGING}/files/shared/"
else
    cp -a "${ROOT}/shared/." "${STAGING}/files/shared/"
    rm -rf "${STAGING}/files/shared/backups" "${STAGING}/files/shared/node_modules"
fi

# Database dump (optional if no managed DB is linked).
if [ -n "${DB_NAME}" ] && [ "${DB_NAME}" != "''" ] && [ "${DB_NAME}" != '""' ]; then
    # escapeshellarg from PHP may wrap values in single quotes — strip for shell use.
    ENGINE_CLEAN="${DB_ENGINE//\'/}"
    NAME_CLEAN="${DB_NAME//\'/}"
    if [ "${ENGINE_CLEAN}" = "postgresql" ]; then
        sudo -u postgres pg_dump --no-owner --no-acl "${NAME_CLEAN}" | gzip -c > "${STAGING}/database.sql.gz"
    else
        mysqldump --protocol=socket --single-transaction --routines --triggers "${NAME_CLEAN}" | gzip -c > "${STAGING}/database.sql.gz"
    fi
elif [ "${PLATFORM//\'/}" = "wordpress" ]; then
    if [ ! -x /usr/local/bin/wp ]; then
        curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x /usr/local/bin/wp
    fi
    sudo -u www-data /usr/local/bin/wp --path="${ROOT}/current" --no-color db export "${STAGING}/database.sql" --add-drop-table
    gzip -f "${STAGING}/database.sql"
else
    echo "No database linked; packing files only." > "${STAGING}/DATABASE_SKIPPED.txt"
fi

printf '%s\n' "${PLATFORM//\'/}" > "${STAGING}/platform.txt"
printf '%s\n' "${DOMAIN//\'/}" > "${STAGING}/domain.txt"

tar -C "${STAGING}" -czf "${ARCHIVE}" .
BYTES="$(wc -c < "${ARCHIVE}" | tr -d ' ')"
rm -rf "${STAGING}"

echo "CLOUDDECK_ARCHIVE_PATH=${ARCHIVE}"
echo "CLOUDDECK_BACKUP_BYTES=${BYTES}"
echo "Full application backup ${BACKUP_ID} ready"
