#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
RELEASE={{RELEASE}}
PHP_VERSION={{PHP_VERSION}}
PLATFORM={{PLATFORM}}
ROOT="/var/www/${DOMAIN}"
TARGET="${ROOT}/releases/${RELEASE}"

test -d "${TARGET}"
if [ "${PLATFORM}" != "react" ]; then
    test -f "${TARGET}/artisan"
fi
if [ -e "${ROOT}/current" ] && [ ! -L "${ROOT}/current" ]; then
    echo "Removing non-symlink ${ROOT}/current so the release can be linked"
    rm -rf "${ROOT}/current"
fi
ln -sfn "${TARGET}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"
if [ "${PLATFORM}" != "react" ]; then
    cd "${ROOT}/current"
    php artisan queue:restart || true
    systemctl reload "php${PHP_VERSION}-fpm"
fi
systemctl reload nginx
echo "Rolled back to ${RELEASE}"
