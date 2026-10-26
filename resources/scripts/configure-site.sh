#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
PHP_VERSION={{PHP_VERSION}}
DOCUMENT_ROOT={{DOCUMENT_ROOT}}
PLATFORM={{PLATFORM}}
MULTISITE_ORIGIN={{MULTISITE_ORIGIN}}
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
elif [ -f "${PLACEHOLDER}/index.html" ] && ! grep -q "<h1>${DOMAIN}</h1>" "${PLACEHOLDER}/index.html"; then
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

  TARGET_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/uplary-${DOMAIN}.conf"

  # A PHP-version change used to leave the site's old pool behind. Both FPM masters
  # would then claim the same per-site socket and the newly selected version could not
  # start. Remove the stale pool and reload its owner before creating the new one.
  for pool in /etc/php/*/fpm/pool.d/uplary-"${DOMAIN}".conf; do
    if [ ! -e "${pool}" ] || [ "${pool}" = "${TARGET_POOL}" ]; then
      continue
    fi
    old_version="${pool#/etc/php/}"
    old_version="${old_version%%/*}"
    rm -f "${pool}"
    if systemctl is-active --quiet "php${old_version}-fpm"; then
      systemctl reload "php${old_version}-fpm"
    fi
  done

  cat > "${TARGET_POOL}" <<POOL
[uplary-${DOMAIN}]
user = www-data
group = www-data
listen = /run/php/uplary-${DOMAIN}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
POOL
  "php-fpm${PHP_VERSION}" -t
  # The package can remain installed while the service is stopped after a reboot or
  # manual operation. reload refuses to start an inactive unit and blocks every retry.
  systemctl enable "php${PHP_VERSION}-fpm"
  systemctl reload-or-restart "php${PHP_VERSION}-fpm"
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

    include /etc/nginx/uplary/${DOMAIN}-reverb.conf*;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/uplary-${DOMAIN}.sock;
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

# Cloudflare and other TLS frontends connect to origin :443. configure-site only writes
# port 80 until Certbot runs, so unmatched HTTPS Host headers fall through to whichever
# site nginx loads first. A temporary self-signed listener keeps this hostname on its
# own vhost until Let's Encrypt replaces the certificate.
ensure_https_listener() {
  if [ ! -f "${NGINX_SITE}" ]; then
    return
  fi
  if grep -qE 'listen[[:space:]]+443' "${NGINX_SITE}"; then
    echo "HTTPS already configured for ${DOMAIN}"
    return
  fi

  CERT_DIR="/etc/ssl/uplary/${DOMAIN}"
  mkdir -p "${CERT_DIR}"
  if [ ! -f "${CERT_DIR}/fullchain.pem" ]; then
    openssl req -x509 -nodes -days 90 -newkey rsa:2048 \
      -keyout "${CERT_DIR}/privkey.pem" \
      -out "${CERT_DIR}/fullchain.pem" \
      -subj "/CN=${DOMAIN}" >/dev/null 2>&1
    chmod 644 "${CERT_DIR}/fullchain.pem"
    chmod 600 "${CERT_DIR}/privkey.pem"
  fi

  FULLCHAIN="${CERT_DIR}/fullchain.pem"
  PRIVKEY="${CERT_DIR}/privkey.pem"
  TMP="${NGINX_SITE}.https.tmp"

  awk -v fullchain="${FULLCHAIN}" -v privkey="${PRIVKEY}" '
    BEGIN { inserted = 0 }
    {
      print
      if (!inserted && $0 ~ /^[[:space:]]*server_name[[:space:]]+/) {
        print "    listen 443 ssl;"
        print "    listen [::]:443 ssl;"
        print "    ssl_certificate " fullchain ";"
        print "    ssl_certificate_key " privkey ";"
        inserted = 1
      }
    }
    END {
      if (!inserted) {
        print "Could not add HTTPS listener: server_name not found" > "/dev/stderr"
        exit 1
      }
    }
  ' "${NGINX_SITE}" > "${TMP}"
  mv "${TMP}" "${NGINX_SITE}"
  echo "Added temporary HTTPS listener for ${DOMAIN}"
}

ensure_https_listener

# A multisite application resolves tenants from the incoming Host header. Its fallback
# vhost therefore catches only hostnames without an explicit Nginx server_name; normal
# Uplary sites on the same server always keep priority. The marker prevents disabling a
# different site's catch-all when an older job is retried.
configure_multisite_origin() {
  MULTISITE_SITE="/etc/nginx/sites-available/uplary-multisite-origin"
  MULTISITE_LINK="/etc/nginx/sites-enabled/00-uplary-multisite-origin"
  MARKER="# UPLARY_MULTISITE_ORIGIN=${DOMAIN}"

  if [ "${MULTISITE_ORIGIN}" != "1" ]; then
    if [ -f "${MULTISITE_SITE}" ] && grep -Fqx "${MARKER}" "${MULTISITE_SITE}"; then
      rm -f "${MULTISITE_LINK}" "${MULTISITE_SITE}"
      echo "Removed the multisite origin for ${DOMAIN}"
    fi
    return
  fi

  if [ "${PLATFORM}" != "laravel" ]; then
    echo "A multisite origin must be a Laravel site." >&2
    exit 1
  fi

  CERT_DIR="/etc/ssl/uplary/${DOMAIN}"
  mkdir -p "${CERT_DIR}"
  if [ ! -f "${CERT_DIR}/fullchain.pem" ] || [ ! -f "${CERT_DIR}/privkey.pem" ]; then
    openssl req -x509 -nodes -days 90 -newkey rsa:2048 \
      -keyout "${CERT_DIR}/privkey.pem" \
      -out "${CERT_DIR}/fullchain.pem" \
      -subj "/CN=${DOMAIN}" >/dev/null 2>&1
    chmod 644 "${CERT_DIR}/fullchain.pem"
    chmod 600 "${CERT_DIR}/privkey.pem"
  fi

  MULTISITE_ROOT="/var/www/${DOMAIN}/${DOCUMENT_ROOT}"
  TMP="${MULTISITE_SITE}.tmp"
  cat > "${TMP}" <<NGINX
${MARKER}
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root ${MULTISITE_ROOT};
    index index.php index.html;
    charset utf-8;
    client_max_body_size 100M;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/uplary-${DOMAIN}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/${DOMAIN}.multisite.access.log;
    error_log /var/log/nginx/${DOMAIN}.multisite.error.log;
}

server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    server_name _;
    root ${MULTISITE_ROOT};
    index index.php index.html;
    charset utf-8;
    client_max_body_size 100M;
    ssl_certificate ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/uplary-${DOMAIN}.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
    access_log /var/log/nginx/${DOMAIN}.multisite.access.log;
    error_log /var/log/nginx/${DOMAIN}.multisite.error.log;
}
NGINX
  mv "${TMP}" "${MULTISITE_SITE}"
  ln -sfn "${MULTISITE_SITE}" "${MULTISITE_LINK}"
  echo "Configured ${DOMAIN} as the multisite origin"
}

configure_multisite_origin

nginx -t
systemctl reload nginx
