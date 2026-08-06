#!/usr/bin/env bash
# Issue or renew Let's Encrypt TLS for the Uplary control-plane hostname on this host.
# Invoked only from Super Admin → Platform services (Linux), never over SSH to customer servers.
set -Eeuo pipefail

DOMAIN={{DOMAIN}}
EMAIL={{EMAIL}}

if [[ -z "${DOMAIN}" || "${DOMAIN}" == "localhost" || "${DOMAIN}" == "127.0.0.1" ]]; then
    echo "Refusing to run Certbot for a local/empty domain: '${DOMAIN}'" >&2
    exit 1
fi

if ! command -v certbot >/dev/null 2>&1; then
    echo "certbot is not installed on this host." >&2
    exit 1
fi

if [[ -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
    certbot renew --cert-name "${DOMAIN}" --non-interactive --quiet
else
    certbot --nginx --non-interactive --agree-tos --keep-until-expiring \
        --email "${EMAIL}" -d "${DOMAIN}" --redirect
fi

nginx -t
systemctl reload nginx
