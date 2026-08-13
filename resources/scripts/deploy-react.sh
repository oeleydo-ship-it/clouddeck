#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
REPOSITORY={{REPOSITORY}}
BRANCH={{BRANCH}}
RELEASE={{RELEASE}}
ENVIRONMENT_BASE64={{ENVIRONMENT_BASE64}}
CUSTOM_SCRIPT_BASE64={{CUSTOM_SCRIPT_BASE64}}
ROOT="/var/www/${DOMAIN}"
RELEASE_PATH="${ROOT}/releases/${RELEASE}"
SHARED="${ROOT}/shared"
EXPECTED_ROOT="${ROOT}/current/dist"

cleanup_failed_release() { if [ ! -L "${ROOT}/current" ] || [ "$(readlink -f "${ROOT}/current")" != "${RELEASE_PATH}" ]; then rm -rf "${RELEASE_PATH}"; fi; }
trap cleanup_failed_release ERR

ensure_document_root() {
    local vhost="/etc/nginx/sites-available/${DOMAIN}"
    [ -f "${vhost}" ] || return 0
    grep -E "^[[:space:]]*root[[:space:]]+" "${vhost}" | grep -qv "root ${EXPECTED_ROOT};" || return 0
    cp -a "${vhost}" "${vhost}.clouddeck-bak"
    sed -i -E "s|^([[:space:]]*)root[[:space:]]+[^;]+;|\1root ${EXPECTED_ROOT};|" "${vhost}"
    if nginx -t; then
        echo "Corrected the Nginx document root to ${EXPECTED_ROOT}"
    else
        cp -a "${vhost}.clouddeck-bak" "${vhost}"
        echo "Left the Nginx document root alone: the rewritten server block did not validate" >&2
    fi
}

echo "[1/6] Cloning ${BRANCH} into ${RELEASE}"
git clone --depth 1 --branch "${BRANCH}" --single-branch "${REPOSITORY}" "${RELEASE_PATH}"
cd "${RELEASE_PATH}"
COMMIT_HASH="$(git rev-parse HEAD)"
COMMIT_MESSAGE="$(git log -1 --pretty=%s)"
echo "CLOUDDECK_COMMIT=${COMMIT_HASH}"
echo "CLOUDDECK_MESSAGE_BASE64=$(printf '%s' "${COMMIT_MESSAGE}" | base64 -w0)"

echo "[2/6] Linking build environment"
mkdir -p "${SHARED}"
printf '%s' "${ENVIRONMENT_BASE64}" | base64 -d > "${SHARED}/.env"
ln -sfn "${SHARED}/.env" .env

echo "[3/6] Installing npm dependencies and building"
if [ ! -f package.json ]; then
    echo "This repository has no package.json; a React/Vite site needs one." >&2
    exit 1
fi
if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
npm run build

if [ -f dist/index.html ]; then
    echo "Build output is dist/"
elif [ -f build/index.html ]; then
    echo "Build output is build/; linking to dist/ for Nginx"
    rm -rf dist
    ln -sfn build dist
else
    echo "npm run build did not produce dist/index.html or build/index.html." >&2
    exit 1
fi

echo "[4/6] Running custom deployment script"
if [ -n "${CUSTOM_SCRIPT_BASE64}" ]; then
    printf '%s' "${CUSTOM_SCRIPT_BASE64}" | base64 -d > /tmp/clouddeck-custom-deploy.sh
    chmod 700 /tmp/clouddeck-custom-deploy.sh
    /tmp/clouddeck-custom-deploy.sh
    rm -f /tmp/clouddeck-custom-deploy.sh
fi

echo "[5/6] Switching the current release atomically"
chown -R www-data:www-data "${RELEASE_PATH}" "${SHARED}"
if [ -e "${ROOT}/current" ] && [ ! -L "${ROOT}/current" ]; then
    echo "Removing non-symlink ${ROOT}/current so the release can be linked"
    rm -rf "${ROOT}/current"
fi
ln -sfn "${RELEASE_PATH}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"

echo "[6/6] Reloading Nginx and pruning old releases"
ensure_document_root
systemctl reload nginx
cd "${ROOT}/releases"
ls -1dt */ | tail -n +6 | xargs -r rm -rf
echo "Release ${RELEASE} is live"
