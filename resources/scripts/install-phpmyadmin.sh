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

# phpMyAdmin can pull in a newer php-cli package as a dependency, which flips the system-wide
# `php` alternative away from the version CloudDeck manages, breaking deploys and the terminal
# console on every site on this server. Pin it back to the version bootstrap installed.
if [ -x /usr/bin/php8.4 ]; then
    update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true
fi

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
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
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
