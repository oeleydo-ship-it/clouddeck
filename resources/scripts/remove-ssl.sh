#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
DOCUMENT_ROOT={{DOCUMENT_ROOT}}
PLATFORM={{PLATFORM}}
ROOT="/var/www/${DOMAIN}"
DOC_ABS="${ROOT}/${DOCUMENT_ROOT}"
PLACEHOLDER="${ROOT}/shared/placeholder"
NGINX_SITE="/etc/nginx/sites-available/${DOMAIN}"
BACKUP="/etc/clouddeck/backups/nginx/${DOMAIN}-remove-ssl-$(date +%s)"

mkdir -p "$(dirname "${BACKUP}")" "${ROOT}/shared/acme"
if [ -f "${NGINX_SITE}" ]; then
  cp -a "${NGINX_SITE}" "${BACKUP}"
fi

# Drop Let's Encrypt and any uploaded custom PEMs for this hostname.
certbot delete --cert-name "${DOMAIN}" --non-interactive 2>/dev/null || true
rm -rf "/etc/ssl/clouddeck/${DOMAIN}"

if [ -L "${ROOT}/current" ] && [ -d "${DOC_ABS}" ]; then
  NGINX_ROOT="${DOC_ABS}"
elif [ -d "${PLACEHOLDER}" ]; then
  NGINX_ROOT="${PLACEHOLDER}"
else
  NGINX_ROOT="${DOC_ABS}"
  mkdir -p "${NGINX_ROOT}"
fi

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

ln -sfn "${NGINX_SITE}" "/etc/nginx/sites-enabled/${DOMAIN}"

if ! nginx -t; then
  if [ -f "${BACKUP}" ]; then
    cp -a "${BACKUP}" "${NGINX_SITE}"
    nginx -t || true
  fi
  echo "Nginx config failed after removing SSL; restored the previous site block." >&2
  exit 1
fi

systemctl reload nginx
echo "SSL removed for ${DOMAIN}; site is serving HTTP only."
