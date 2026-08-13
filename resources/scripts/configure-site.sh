#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
DOCUMENT_ROOT={{DOCUMENT_ROOT}}
PLATFORM={{PLATFORM}}
ROOT="/var/www/${DOMAIN}"
# Never mkdir under current/: that path must stay free for a release symlink.
# A real current/ directory makes `mv -T current.next current` fail with
# "cannot overwrite directory … with non-directory".
PLACEHOLDER="${ROOT}/shared/placeholder"
DOC_ABS="${ROOT}/${DOCUMENT_ROOT}"
NGINX_SITE="/etc/nginx/sites-available/${DOMAIN}"

mkdir -p "${ROOT}/releases" \
  "${ROOT}/shared/storage/app/public" \
  "${ROOT}/shared/storage/framework/cache" \
  "${ROOT}/shared/storage/framework/sessions" \
  "${ROOT}/shared/storage/framework/views" \
  "${ROOT}/shared/storage/logs" \
  "${ROOT}/shared/acme" \
  "${PLACEHOLDER}"
chown -R www-data:www-data "${ROOT}"

# Before the first deploy, current/public does not exist. Without a real document root
# Nginx still needs a matching server_name block; otherwise requests fall through to the
# distro default welcome page and Let's Encrypt challenges fail on the wrong vhost.
if [ ! -f "${PLACEHOLDER}/index.php" ] && [ ! -f "${PLACEHOLDER}/index.html" ]; then
  cat > "${PLACEHOLDER}/index.html" <<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>${DOMAIN}</title></head>
<body style="font-family:system-ui;margin:3rem;line-height:1.5">
<h1>${DOMAIN}</h1>
<p>This site is configured on the server. Deploy from Uplary to publish the application.</p>
</body></html>
HTML
  chown www-data:www-data "${PLACEHOLDER}/index.html"
fi

if [ -L "${ROOT}/current" ] && [ -d "${DOC_ABS}" ]; then
  NGINX_ROOT="${DOC_ABS}"
else
  NGINX_ROOT="${PLACEHOLDER}"
fi

if [ "${PLATFORM}" != "react" ]; then
  if [ ! -d "/etc/php/${PHP_VERSION}/fpm/pool.d" ]; then
    echo "PHP ${PHP_VERSION} FPM is not installed (missing /etc/php/${PHP_VERSION}/fpm/pool.d)." >&2
    exit 1
  fi

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
fi

write_http_vhost() {
  if [ "${PLATFORM}" = "react" ]; then
cat > "${NGINX_SITE}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${NGINX_ROOT};
    index index.html;
    charset utf-8;
    client_max_body_size 100M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location ^~ /.well-known/acme-challenge/ {
        root ${ROOT}/shared/acme;
        default_type text/plain;
    }

    location / { try_files \$uri \$uri/ /index.html; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log /var/log/nginx/${DOMAIN}.error.log;
}
NGINX
  else
cat > "${NGINX_SITE}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${NGINX_ROOT};
    index index.php index.html;
    charset utf-8;
    client_max_body_size 100M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Shared ACME webroot so certificates can issue before the first deploy lands.
    location ^~ /.well-known/acme-challenge/ {
        root ${ROOT}/shared/acme;
        default_type text/plain;
    }

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
  fi
  echo "Wrote the Nginx server block for ${DOMAIN}"
}

# Written only when the site has none, or when the block is missing this hostname.
# Never clobber a Certbot-managed file — that would drop TLS lines.
if [ ! -f "${NGINX_SITE}" ]; then
  write_http_vhost
elif ! grep -qE "server_name[[:space:]]+${DOMAIN}([:;[:space:]]|$)" "${NGINX_SITE}"; then
  if grep -q "managed by Certbot" "${NGINX_SITE}"; then
    echo "Nginx site exists with Certbot changes but unexpected server_name; leaving intact"
  else
    write_http_vhost
  fi
else
  echo "Nginx server block for ${DOMAIN} already present"
fi

# Without this link Nginx has no block for the domain and answers with whichever site it
# lists first, so the domain quietly serves the default welcome page.
ln -sfn "${NGINX_SITE}" "/etc/nginx/sites-enabled/${DOMAIN}"

# Stock Ubuntu welcome site is default_server and steals unmatched Host headers. App
# servers should not keep it enabled once real sites exist.
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx
