#!/usr/bin/env bash
set -Eeuo pipefail

# Restore a full application archive previously staged at ARCHIVE_PATH.
# Expects domain confirmation to have been enforced by the control plane.

DOMAIN={{DOMAIN}}
ARCHIVE_PATH={{ARCHIVE_PATH}}
PHP_VERSION={{PHP_VERSION}}
DB_ENGINE={{DB_ENGINE}}
DB_NAME={{DB_NAME}}

ROOT="/var/www/${DOMAIN}"
WORK="/tmp/uplary-site-restore-$$"
SAFETY="${ROOT}/shared/backups/pre-restore-$(date +%Y%m%d%H%M%S)"

if [ ! -f "${ARCHIVE_PATH}" ]; then
    echo "Archive missing: ${ARCHIVE_PATH}" >&2
    exit 1
fi

mkdir -p "${WORK}" "${SAFETY}"
tar -C "${WORK}" -xzf "${ARCHIVE_PATH}"

if [ ! -d "${WORK}/files/current" ] || [ ! -d "${WORK}/files/shared" ]; then
    echo "Archive is missing files/current or files/shared" >&2
    exit 1
fi

# Capture current shared state for emergency rollback on the VPS.
if [ -d "${ROOT}/shared" ]; then
    mkdir -p "${ROOT}/shared/backups"
    if command -v rsync >/dev/null 2>&1; then
        rsync -a --exclude 'backups/' "${ROOT}/shared/" "${SAFETY}/shared/" || true
    else
        mkdir -p "${SAFETY}/shared"
        cp -a "${ROOT}/shared/." "${SAFETY}/shared/" 2>/dev/null || true
        rm -rf "${SAFETY}/shared/backups"
    fi
fi

RELEASE="restore-$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${ROOT}/releases/${RELEASE}"
mkdir -p "${ROOT}/releases"
rm -rf "${RELEASE_DIR}"
cp -a "${WORK}/files/current" "${RELEASE_DIR}"

# Merge shared (preserve existing backups directory).
mkdir -p "${ROOT}/shared"
if [ -d "${ROOT}/shared/backups" ]; then
    KEEP_BACKUPS=1
else
    KEEP_BACKUPS=0
fi
if command -v rsync >/dev/null 2>&1; then
    rsync -a --exclude 'backups/' "${WORK}/files/shared/" "${ROOT}/shared/"
else
    cp -a "${WORK}/files/shared/." "${ROOT}/shared/"
fi
if [ "${KEEP_BACKUPS}" = "1" ]; then
    mkdir -p "${ROOT}/shared/backups"
fi

# Point current at the restored release.
if [ -e "${ROOT}/current" ] && [ ! -L "${ROOT}/current" ]; then
    echo "Removing non-symlink ${ROOT}/current so the release can be linked"
    rm -rf "${ROOT}/current"
fi
ln -sfn "${RELEASE_DIR}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"

# Import database when present.
if [ -f "${WORK}/database.sql.gz" ]; then
    ENGINE_CLEAN="${DB_ENGINE//\'/}"
    NAME_CLEAN="${DB_NAME//\'/}"
    gunzip -c "${WORK}/database.sql.gz" > "${WORK}/database.sql"
    if [ -n "${NAME_CLEAN}" ] && [ "${NAME_CLEAN}" != "" ]; then
        if [ "${ENGINE_CLEAN}" = "postgresql" ]; then
            sudo -u postgres psql -v ON_ERROR_STOP=1 -d "${NAME_CLEAN}" -f "${WORK}/database.sql"
        else
            mysql --protocol=socket "${NAME_CLEAN}" < "${WORK}/database.sql"
        fi
    elif [ -x /usr/local/bin/wp ]; then
        sudo -u www-data /usr/local/bin/wp --path="${ROOT}/current" --no-color db import "${WORK}/database.sql"
    fi
fi

chown -R www-data:www-data "${ROOT}/shared" "${RELEASE_DIR}" || true
if command -v systemctl >/dev/null 2>&1; then
    systemctl reload "php${PHP_VERSION//\'/}-fpm" 2>/dev/null || true
    systemctl reload nginx 2>/dev/null || true
fi

# Keep a handful of releases.
cd "${ROOT}/releases"
ls -1dt */ 2>/dev/null | tail -n +6 | xargs -r rm -rf

rm -rf "${WORK}" "${ARCHIVE_PATH}"
echo "Restore complete for ${DOMAIN//\'/}"
