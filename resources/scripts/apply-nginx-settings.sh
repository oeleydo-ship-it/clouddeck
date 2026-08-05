#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
CLIENT_MAX_BODY_MB={{CLIENT_MAX_BODY_MB}}
STATIC_CACHE={{STATIC_CACHE}}
INCLUDE_WWW={{INCLUDE_WWW}}
ALLOW_IFRAME_EMBEDDING={{ALLOW_IFRAME_EMBEDDING}}
SSL_ENABLED={{SSL_ENABLED}}
DOCUMENT_ROOT={{DOCUMENT_ROOT}}
ROOT="/var/www/${DOMAIN}"
TARGET="/etc/nginx/sites-available/${DOMAIN}"
BACKUP="/etc/clouddeck/backups/nginx/${DOMAIN}-$(date +%s)"
TEMP=$(mktemp /etc/nginx/sites-available/.clouddeck-XXXXXX)
mkdir -p "$(dirname "$BACKUP")"
[[ -S "/run/php/clouddeck-${DOMAIN}.sock" ]] || { echo 'Apply PHP pool settings before Nginx settings' >&2; exit 1; }
SERVER_NAMES="$DOMAIN"
[[ "$INCLUDE_WWW" == "1" ]] && SERVER_NAMES="$DOMAIN www.$DOMAIN"
STATIC_BLOCK=""
if [[ "$STATIC_CACHE" == "1" ]]; then
    STATIC_BLOCK='location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|webp|woff2?)$ { expires 30d; access_log off; try_files $uri /index.php?$query_string; }'
fi
# Chat widgets and similar embeds load this site in a cross-origin iframe. SAMEORIGIN
# blocks that (blank gray rectangle on the host page). Opt in only when the site is
# meant to be framed; clickjacking protection stays on by default.
FRAME_OPTIONS_HEADER='    add_header X-Frame-Options "SAMEORIGIN" always;'
if [[ "$ALLOW_IFRAME_EMBEDDING" == "1" ]]; then
    FRAME_OPTIONS_HEADER=''
fi
TLS_LINES=""
if [[ "$SSL_ENABLED" == "1" ]]; then
    TLS_LINES="listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;"
    cat > "$TEMP" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAMES};
    return 301 https://\$host\$request_uri;
}
NGINX
fi
cat >> "$TEMP" <<NGINX
server {
    $([[ "$SSL_ENABLED" == "1" ]] || echo 'listen 80;')
    $([[ "$SSL_ENABLED" == "1" ]] || echo 'listen [::]:80;')
    ${TLS_LINES}
    server_name ${SERVER_NAMES};
    root ${ROOT}/${DOCUMENT_ROOT};
    index index.php;
    charset utf-8;
    client_max_body_size ${CLIENT_MAX_BODY_MB}M;
${FRAME_OPTIONS_HEADER}
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    include /etc/nginx/clouddeck/${DOMAIN}-reverb.conf*;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/clouddeck-${DOMAIN}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    ${STATIC_BLOCK}
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/${DOMAIN}.access.log;
    error_log /var/log/nginx/${DOMAIN}.error.log;
}
NGINX
[[ -f "$TARGET" ]] && cp -a "$TARGET" "$BACKUP"
mv "$TEMP" "$TARGET"
ln -sfn "$TARGET" "/etc/nginx/sites-enabled/${DOMAIN}"
if ! nginx -t; then
    [[ -f "$BACKUP" ]] && cp -a "$BACKUP" "$TARGET" || rm -f "$TARGET"
    nginx -t || true
    exit 1
fi
systemctl reload nginx
echo "Nginx configuration applied"
