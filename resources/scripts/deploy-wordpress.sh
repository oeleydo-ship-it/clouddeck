#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
RELEASE={{RELEASE}}
PHP_VERSION={{PHP_VERSION}}
WP_CONFIG_BASE64={{WP_CONFIG_BASE64}}
ROOT="/var/www/${DOMAIN}"
RELEASE_PATH="${ROOT}/releases/${RELEASE}"
SHARED="${ROOT}/shared"

cleanup_failed_release() { if [ ! -L "${ROOT}/current" ] || [ "$(readlink -f "${ROOT}/current")" != "${RELEASE_PATH}" ]; then rm -rf "${RELEASE_PATH}"; fi; }
trap cleanup_failed_release ERR

echo "[1/6] Downloading WordPress"
mkdir -p "${RELEASE_PATH}"
curl -fsSL https://wordpress.org/latest.tar.gz | tar xz --strip-components=1 -C "${RELEASE_PATH}"
echo "CLOUDDECK_WP_VERSION=$(grep "wp_version =" "${RELEASE_PATH}/wp-includes/version.php" | head -1 | cut -d"'" -f2)"

echo "[2/6] Linking persistent state"
# wp-content holds uploads, plugins, and themes. It lives outside the release, or every
# deployment would replace the customer's media library with an empty directory.
mkdir -p "${SHARED}/wp-content/uploads"
if [ ! -d "${SHARED}/wp-content/themes" ] || [ -z "$(ls -A "${SHARED}/wp-content/themes" 2>/dev/null)" ]; then
    cp -a "${RELEASE_PATH}/wp-content/." "${SHARED}/wp-content/"
fi
rm -rf "${RELEASE_PATH}/wp-content"
ln -s "${SHARED}/wp-content" "${RELEASE_PATH}/wp-content"

printf '%s' "${WP_CONFIG_BASE64}" | base64 -d > "${SHARED}/wp-config.php"
chmod 640 "${SHARED}/wp-config.php"
ln -sfn "${SHARED}/wp-config.php" "${RELEASE_PATH}/wp-config.php"

echo "[4/6] Securing the install"
# Shipped for the installer's benefit and never needed again; leaving it exposes the
# database credentials form to anyone who finds the URL.
rm -f "${RELEASE_PATH}/wp-config-sample.php" "${RELEASE_PATH}/readme.html" "${RELEASE_PATH}/license.txt"

echo "[5/6] Switching the current release atomically"
chown -R www-data:www-data "${RELEASE_PATH}" "${SHARED}/wp-content" "${SHARED}/wp-config.php"
find "${RELEASE_PATH}" -type d -exec chmod 755 {} \;
find "${RELEASE_PATH}" -type f -exec chmod 644 {} \;
ln -sfn "${RELEASE_PATH}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"

echo "[6/6] Reloading services and pruning old releases"
systemctl reload "php${PHP_VERSION}-fpm"
systemctl reload nginx
cd "${ROOT}/releases"
ls -1dt */ | tail -n +6 | xargs -r rm -rf
echo "Release ${RELEASE} is live"
