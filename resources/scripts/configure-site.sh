#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
DOCUMENT_ROOT={{DOCUMENT_ROOT}}
ROOT="/var/www/${DOMAIN}"

mkdir -p "${ROOT}/releases" "${ROOT}/shared/storage/app/public" "${ROOT}/shared/storage/framework/cache" "${ROOT}/shared/storage/framework/sessions" "${ROOT}/shared/storage/framework/views" "${ROOT}/shared/storage/logs"
chown -R www-data:www-data "${ROOT}"

cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/clouddeck-${DOMAIN}.conf" <<POOL
[clouddeck-${DOMAIN}]
user = www-data
group = www-data
listen = /run/php/clouddeck-${DOMAIN}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
POOL
"php-fpm${PHP_VERSION}" -t
systemctl reload "php${PHP_VERSION}-fpm"

cat > "/etc/nginx/sites-available/${DOMAIN}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${ROOT}/${DOCUMENT_ROOT};
    index index.php;
    charset utf-8;
    client_max_body_size 100M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    include /etc/nginx/clouddeck/${DOMAIN}-reverb.conf*;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/clouddeck-${DOMAIN}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log /var/log/nginx/${DOMAIN}.error.log;
}
NGINX

ln -sfn "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
nginx -t
systemctl reload nginx
