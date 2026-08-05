#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PACKAGE={{PACKAGE}}
INSTALL_COMMAND={{INSTALL_COMMAND}}
ROOT="/var/www/${DOMAIN}/current"

cd "${ROOT}"
sudo -u www-data composer require "${PACKAGE}" --no-interaction --no-progress
if [ -n "${INSTALL_COMMAND}" ]; then
    sudo -u www-data php artisan "${INSTALL_COMMAND}" --no-interaction
fi

if [ "${PACKAGE}" = "laravel/horizon" ] && [ -f app/Providers/HorizonServiceProvider.php ]; then
    sudo -u www-data tee app/Providers/HorizonServiceProvider.php > /dev/null <<'PHP'
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
            if (! $user) {
                return false;
            }

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            if (! empty($user->is_admin)) {
                return true;
            }

            $candidates = [
                storage_path('app/uplary-horizon-admins.txt'),
                storage_path('app/Uplary-horizon-admins.txt'),
                storage_path('app/clouddeck-horizon-admins.txt'),
            ];

            $file = null;
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $file = $candidate;
                    break;
                }
            }

            if ($file === null) {
                return false;
            }

            $allowed = array_values(array_filter(array_map(
                static fn ($line) => strtolower(trim((string) $line)),
                file($file) ?: []
            )));

            if ($allowed === []) {
                return false;
            }

            return in_array(strtolower(trim((string) ($user->email ?? ''))), $allowed, true);
        });
    }
}
PHP
    touch storage/app/uplary-horizon-admins.txt
    touch storage/app/clouddeck-horizon-admins.txt 2>/dev/null || true
    chown www-data:www-data storage/app/uplary-horizon-admins.txt storage/app/clouddeck-horizon-admins.txt 2>/dev/null || true
fi

# A newly required package can register routes, config, or service providers that a stale cache
# from the last deploy won't know about (Horizon's /horizon/* routes, config/horizon.php, etc.).
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache

echo "Installed ${PACKAGE} into the current release. Uplary will keep it installed on every future deployment."
