#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
RELEASE={{RELEASE}}
PHP_VERSION={{PHP_VERSION}}
ROOT="/var/www/${DOMAIN}"
TARGET="${ROOT}/releases/${RELEASE}"

test -d "${TARGET}"
test -f "${TARGET}/artisan"
if [ -e "${ROOT}/current" ] && [ ! -L "${ROOT}/current" ]; then
    echo "Removing non-symlink ${ROOT}/current so the release can be linked"
    rm -rf "${ROOT}/current"
fi
ln -sfn "${TARGET}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"
cd "${ROOT}/current"
php artisan queue:restart || true
systemctl reload "php${PHP_VERSION}-fpm"
systemctl reload nginx
echo "Rolled back to ${RELEASE}"
