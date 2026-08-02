#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}

rm -f "/etc/nginx/sites-enabled/${DOMAIN}" "/etc/nginx/sites-available/${DOMAIN}"
if nginx -t 2>/dev/null; then systemctl reload nginx; fi

rm -f "/etc/php/${PHP_VERSION}/fpm/pool.d/clouddeck-${DOMAIN}.conf"
if "php-fpm${PHP_VERSION}" -t 2>/dev/null; then systemctl reload "php${PHP_VERSION}-fpm"; fi

certbot delete --cert-name "${DOMAIN}" --non-interactive 2>/dev/null || true
rm -rf "/var/www/${DOMAIN}"
echo "Site ${DOMAIN} removed"
