#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
REPOSITORY={{REPOSITORY}}
BRANCH={{BRANCH}}
RELEASE={{RELEASE}}
PHP_VERSION={{PHP_VERSION}}
ENVIRONMENT_BASE64={{ENVIRONMENT_BASE64}}
CUSTOM_SCRIPT_BASE64={{CUSTOM_SCRIPT_BASE64}}
MANAGED_PACKAGES={{MANAGED_PACKAGES}}
ROOT="/var/www/${DOMAIN}"
RELEASE_PATH="${ROOT}/releases/${RELEASE}"
SHARED="${ROOT}/shared"

cleanup_failed_release() { if [ ! -L "${ROOT}/current" ] || [ "$(readlink -f "${ROOT}/current")" != "${RELEASE_PATH}" ]; then rm -rf "${RELEASE_PATH}"; fi; }
trap cleanup_failed_release ERR

echo "[1/9] Cloning ${BRANCH} into ${RELEASE}"
git clone --depth 1 --branch "${BRANCH}" --single-branch "${REPOSITORY}" "${RELEASE_PATH}"
cd "${RELEASE_PATH}"
COMMIT_HASH="$(git rev-parse HEAD)"
COMMIT_MESSAGE="$(git log -1 --pretty=%s)"
echo "CLOUDDECK_COMMIT=${COMMIT_HASH}"
echo "CLOUDDECK_MESSAGE_BASE64=$(printf '%s' "${COMMIT_MESSAGE}" | base64 -w0)"

echo "[2/9] Installing Composer dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -n "${MANAGED_PACKAGES}" ]; then
    echo "Installing CloudDeck-managed packages:${MANAGED_PACKAGES}"
    for pkg in ${MANAGED_PACKAGES}; do
        composer require "${pkg}" --no-interaction --no-progress
    done
fi

echo "[3/9] Linking persistent state"
printf '%s' "${ENVIRONMENT_BASE64}" | base64 -d > "${SHARED}/.env"
rm -rf storage
ln -s "${SHARED}/storage" storage
ln -sfn "${SHARED}/.env" .env
mkdir -p bootstrap/cache
chmod -R ug+rwX "${SHARED}/storage" bootstrap/cache
grep -qE '^APP_KEY="?base64:' "${SHARED}/.env" || php artisan key:generate --force

if [ -n "${MANAGED_PACKAGES}" ]; then
    for pkg in ${MANAGED_PACKAGES}; do
        case "${pkg}" in
            laravel/horizon)
                php artisan horizon:install --no-interaction || true
                if [ -f app/Providers/HorizonServiceProvider.php ]; then
                    tee app/Providers/HorizonServiceProvider.php > /dev/null <<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            $file = storage_path('app/clouddeck-horizon-admins.txt');
            if (! is_file($file)) {
                return false;
            }

            $allowed = array_filter(array_map('trim', array_map('strtolower', file($file))));

            return in_array(strtolower((string) ($user->email ?? '')), $allowed, true);
        });
    }
}
PHP
                    touch storage/app/clouddeck-horizon-admins.txt
                fi
                ;;
            laravel/reverb) php artisan reverb:install --no-interaction || true ;;
        esac
    done
fi

echo "[4/9] Running database migrations"
php artisan migrate --force
php artisan storage:link --force

echo "[5/9] Building frontend assets"
if [ -f package.json ]; then
    if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
    npm run build --if-present
fi

echo "[6/9] Optimizing Laravel"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[7/9] Running custom deployment script"
if [ -n "${CUSTOM_SCRIPT_BASE64}" ]; then
    printf '%s' "${CUSTOM_SCRIPT_BASE64}" | base64 -d > /tmp/clouddeck-custom-deploy.sh
    chmod 700 /tmp/clouddeck-custom-deploy.sh
    /tmp/clouddeck-custom-deploy.sh
    rm -f /tmp/clouddeck-custom-deploy.sh
fi

echo "[8/9] Switching the current release atomically"
chown -R www-data:www-data "${RELEASE_PATH}" "${SHARED}"
ln -sfn "${RELEASE_PATH}" "${ROOT}/current.next"
mv -Tf "${ROOT}/current.next" "${ROOT}/current"

echo "[9/9] Reloading services and pruning old releases"
php artisan queue:restart || true
systemctl reload "php${PHP_VERSION}-fpm"
systemctl reload nginx
cd "${ROOT}/releases"
ls -1dt */ | tail -n +6 | xargs -r rm -rf
echo "Release ${RELEASE} is live"
