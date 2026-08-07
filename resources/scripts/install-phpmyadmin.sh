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

# Prefer an FPM socket that actually exists (starting the unit if needed).
# Do not pick by newest php-cli alone: `apt install phpmyadmin` often pulls a
# newer phpX.Y-cli without installing/starting phpX.Y-fpm, which yields nginx 502.
PHP_SOCK=""
PHP_VER=""
for candidate in 8.5 8.4 8.3 8.2; do
    sock="/run/php/php${candidate}-fpm.sock"
    unit="php${candidate}-fpm"
    if systemctl cat "${unit}" >/dev/null 2>&1; then
        systemctl enable --now "${unit}" 2>/dev/null || true
    fi
    if [ -S "${sock}" ]; then
        PHP_SOCK="${sock}"
        PHP_VER="${candidate}"
        break
    fi
done

if [ -z "${PHP_SOCK}" ]; then
    echo "No PHP-FPM socket found under /run/php/php*-fpm.sock (tried 8.5–8.2). Start php*-fpm and re-run." >&2
    ls -la /run/php 2>/dev/null || true
    systemctl status 'php*-fpm' --no-pager 2>/dev/null || true
    exit 64
fi

# Pin system `php` to the same major we will fastcgi to, so apt's phpmyadmin
# dependency cannot leave Composer/artisan on a different version than FPM.
if [ -x "/usr/bin/php${PHP_VER}" ]; then
    update-alternatives --set php "/usr/bin/php${PHP_VER}" 2>/dev/null || true
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
echo "phpMyAdmin installed on port ${PORT} (fastcgi ${PHP_SOCK})"
