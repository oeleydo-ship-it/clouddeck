#!/usr/bin/env bash
set -Eeuo pipefail
PORT={{PORT}}

rm -f /etc/nginx/sites-enabled/clouddeck-phpmyadmin /etc/nginx/sites-available/clouddeck-phpmyadmin
systemctl reload nginx || true
ufw delete allow "${PORT}/tcp" || true

if [ -x /usr/bin/php8.4 ]; then
    update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true
fi

echo "phpMyAdmin removed"
