#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
EMAIL={{EMAIL}}
REDIRECT={{REDIRECT}}
certbot --nginx --non-interactive --agree-tos --keep-until-expiring --email "${EMAIL}" -d "${DOMAIN}" "--${REDIRECT}"
nginx -t
systemctl reload nginx
