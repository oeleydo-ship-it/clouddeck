#!/usr/bin/env bash
set -Eeuo pipefail
PORT={{PORT}}

if ! dpkg -s phpmyadmin >/dev/null 2>&1; then
    debconf-set-selections <<'DEBCONF'
phpmyadmin phpmyadmin/reconfigure-webserver multiselect
phpmyadmin phpmyadmin/dbconfig-install boolean false
DEBCONF
    DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin
fi

# Prefer the newest managed PHP binary still present so apt's php-cli dependency cannot
# leave Composer/artisan on a different major than site pools.
PHP_BIN=""
PHP_SOCK=""
for candidate in 8.5 8.4 8.3 8.2; do
    if [ -x "/usr/bin/php${candidate}" ]; then
        PHP_BIN="/usr/bin/php${candidate}"
        PHP_SOCK="/run/php/php${candidate}-fpm.sock"
        break
    fi
done
if [ -n "$PHP_BIN" ]; then
    update-alternatives --set php "$PHP_BIN" 2>/dev/null || true
fi
: "${PHP_SOCK:=/run/php/php8.5-fpm.sock}"

cat > /etc/nginx/sites-available/clouddeck-phpmyadmin <<NGINX
server {
    listen ${PORT};
    listen [::]:${PORT};
    server_name _;
    root /usr/share/phpmyadmin;
    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / { try_files \$uri \$uri/ =404; }
    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location ~ ^/(setup|examples|test|sql|libraries)/ { deny all; }
}
NGINX

ln -sfn /etc/nginx/sites-available/clouddeck-phpmyadmin /etc/nginx/sites-enabled/clouddeck-phpmyadmin
nginx -t
systemctl reload nginx
ufw allow "${PORT}/tcp" comment 'clouddeck-phpmyadmin' || true
echo "phpMyAdmin installed on port ${PORT}"
