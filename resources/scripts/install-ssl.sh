#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
EMAIL={{EMAIL}}
REDIRECT={{REDIRECT}}
ROOT="/var/www/${DOMAIN}"
ACME_WEBROOT="${ROOT}/shared/acme"
NGINX_SITE="/etc/nginx/sites-available/${DOMAIN}"

mkdir -p "${ACME_WEBROOT}/.well-known/acme-challenge"
chown -R www-data:www-data "${ROOT}/shared/acme"

if [ ! -f "${NGINX_SITE}" ] || [ ! -e "/etc/nginx/sites-enabled/${DOMAIN}" ]; then
  echo "Nginx site for ${DOMAIN} is missing; refusing to run Certbot until the site is configured." >&2
  exit 1
fi

# Prefer the nginx plugin when the vhost exists; fall back to webroot if the plugin
# cannot complete (common when the default site was answering ACME challenges).
set +e
certbot --nginx --non-interactive --agree-tos --keep-until-expiring --email "${EMAIL}" -d "${DOMAIN}" "--${REDIRECT}" --nginx-sleep-seconds 3
STATUS=$?
set -e

if [ "${STATUS}" -ne 0 ]; then
  echo "Certbot --nginx failed (exit ${STATUS}); retrying with webroot at ${ACME_WEBROOT}"
  certbot certonly --webroot -w "${ACME_WEBROOT}" --non-interactive --agree-tos --keep-until-expiring --email "${EMAIL}" -d "${DOMAIN}"
  # Install the issued cert into Nginx when webroot path was used.
  certbot --nginx --non-interactive --agree-tos --keep-until-expiring --email "${EMAIL}" -d "${DOMAIN}" "--${REDIRECT}" --nginx-sleep-seconds 3
fi

nginx -t
systemctl reload nginx
