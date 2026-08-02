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
    chown www-data:www-data storage/app/clouddeck-horizon-admins.txt
fi

# A newly required package can register routes, config, or service providers that a stale cache
# from the last deploy won't know about (Horizon's /horizon/* routes, config/horizon.php, etc.).
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache

echo "Installed ${PACKAGE} into the current release. CloudDeck will keep it installed on every future deployment."
