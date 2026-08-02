#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
MEMORY_LIMIT_MB={{MEMORY_LIMIT_MB}}
UPLOAD_MAX_MB={{UPLOAD_MAX_MB}}
POST_MAX_MB={{POST_MAX_MB}}
MAX_EXECUTION_TIME={{MAX_EXECUTION_TIME}}
MAX_CHILDREN={{MAX_CHILDREN}}
DISPLAY_ERRORS={{DISPLAY_ERRORS}}
TARGET="/etc/php/${PHP_VERSION}/fpm/pool.d/clouddeck-${DOMAIN}.conf"
BACKUP="/etc/clouddeck/backups/php/clouddeck-${DOMAIN}-$(date +%s).conf"
TEMP=$(mktemp)
mkdir -p "$(dirname "$BACKUP")"
cat > "$TEMP" <<POOL
[clouddeck-${DOMAIN}]
user = www-data
group = www-data
listen = /run/php/clouddeck-${DOMAIN}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = ${MAX_CHILDREN}
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /
php_admin_value[memory_limit] = ${MEMORY_LIMIT_MB}M
php_admin_value[upload_max_filesize] = ${UPLOAD_MAX_MB}M
php_admin_value[post_max_size] = ${POST_MAX_MB}M
php_admin_value[max_execution_time] = ${MAX_EXECUTION_TIME}
php_admin_flag[display_errors] = ${DISPLAY_ERRORS}
POOL
[[ -f "$TARGET" ]] && cp -a "$TARGET" "$BACKUP"
mv "$TEMP" "$TARGET"
if ! "php-fpm${PHP_VERSION}" -t; then
    [[ -f "$BACKUP" ]] && cp -a "$BACKUP" "$TARGET" || rm -f "$TARGET"
    exit 1
fi
systemctl reload "php${PHP_VERSION}-fpm"
echo "PHP-FPM pool configuration applied"
