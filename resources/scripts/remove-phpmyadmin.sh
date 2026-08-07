#!/usr/bin/env bash
set -Eeuo pipefail
PORT={{PORT}}

rm -f /etc/nginx/sites-enabled/clouddeck-phpmyadmin /etc/nginx/sites-available/clouddeck-phpmyadmin
systemctl reload nginx || true
ufw delete allow "${PORT}/tcp" || true

for candidate in 8.5 8.4 8.3 8.2; do
    if [ -x "/usr/bin/php${candidate}" ]; then
        update-alternatives --set php "/usr/bin/php${candidate}" 2>/dev/null || true
        break
    fi
done

echo "phpMyAdmin removed"
