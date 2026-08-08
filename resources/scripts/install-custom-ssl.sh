#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
REDIRECT={{REDIRECT}}
FULLCHAIN_BASE64={{FULLCHAIN_BASE64}}
PRIVKEY_BASE64={{PRIVKEY_BASE64}}
CERT_DIR="/etc/ssl/clouddeck/${DOMAIN}"
FULLCHAIN="${CERT_DIR}/fullchain.pem"
PRIVKEY="${CERT_DIR}/privkey.pem"
TARGET="/etc/nginx/sites-available/${DOMAIN}"
BACKUP="/etc/clouddeck/backups/nginx/${DOMAIN}-custom-ssl-$(date +%s)"

mkdir -p "${CERT_DIR}" "$(dirname "$BACKUP")"
printf '%s' "${FULLCHAIN_BASE64}" | base64 -d > "${FULLCHAIN}"
printf '%s' "${PRIVKEY_BASE64}" | base64 -d > "${PRIVKEY}"
chmod 644 "${FULLCHAIN}"
chmod 600 "${PRIVKEY}"

openssl x509 -in "${FULLCHAIN}" -noout -subject >/dev/null
openssl pkey -in "${PRIVKEY}" -check -noout >/dev/null
openssl x509 -noout -modulus -in "${FULLCHAIN}" | openssl md5 >/tmp/clouddeck-ssl-cert.mod
openssl pkey -noout -modulus -in "${PRIVKEY}" | openssl md5 >/tmp/clouddeck-ssl-key.mod
cmp -s /tmp/clouddeck-ssl-cert.mod /tmp/clouddeck-ssl-key.mod || {
    rm -f /tmp/clouddeck-ssl-cert.mod /tmp/clouddeck-ssl-key.mod
    echo 'Certificate and private key do not match' >&2
    exit 1
}
rm -f /tmp/clouddeck-ssl-cert.mod /tmp/clouddeck-ssl-key.mod

[[ -f "$TARGET" ]] || { echo "Nginx site config missing for ${DOMAIN}" >&2; exit 1; }
cp -a "$TARGET" "$BACKUP"

# Point any existing ssl_certificate directives at the uploaded PEMs.
if grep -qE '^\s*ssl_certificate\s+' "$TARGET"; then
    sed -i -E "s|^\s*ssl_certificate\s+[^;]+;|    ssl_certificate ${FULLCHAIN};|g" "$TARGET"
    sed -i -E "s|^\s*ssl_certificate_key\s+[^;]+;|    ssl_certificate_key ${PRIVKEY};|g" "$TARGET"
else
    # First TLS enablement: add listen/ssl lines after the primary server_name.
    awk -v fullchain="${FULLCHAIN}" -v privkey="${PRIVKEY}" '
        BEGIN { inserted = 0 }
        {
            print
            if (!inserted && $0 ~ /^[[:space:]]*server_name[[:space:]]/) {
                print "    listen 443 ssl http2;"
                print "    listen [::]:443 ssl http2;"
                print "    ssl_certificate " fullchain ";"
                print "    ssl_certificate_key " privkey ";"
                inserted = 1
            }
        }
        END {
            if (!inserted) {
                print "Failed to locate server_name in Nginx config" > "/dev/stderr"
                exit 1
            }
        }
    ' "$BACKUP" > "$TARGET"
fi

# Force HTTPS: ensure a dedicated port-80 redirect server exists when requested.
if [[ "$REDIRECT" == "1" ]]; then
    if ! grep -qE 'return 301 https://' "$TARGET"; then
        {
            echo "server {"
            echo "    listen 80;"
            echo "    listen [::]:80;"
            echo "    server_name ${DOMAIN};"
            echo "    return 301 https://\$host\$request_uri;"
            echo "}"
            echo ""
            cat "$TARGET"
        } > "${TARGET}.tmp"
        mv "${TARGET}.tmp" "$TARGET"
        # Drop listen 80 from the TLS server so only the redirect block owns HTTP.
        awk '
            BEGIN { in_ssl = 0 }
            /^server[[:space:]]*\{/ { in_ssl = 0 }
            /listen 443 ssl/ { in_ssl = 1 }
            {
                if (in_ssl && $0 ~ /^[[:space:]]*listen[[:space:]]+80;/) next
                if (in_ssl && $0 ~ /^[[:space:]]*listen[[:space:]]+\[::\]:80;/) next
                print
            }
        ' "$TARGET" > "${TARGET}.tmp"
        mv "${TARGET}.tmp" "$TARGET"
    fi
fi

if ! nginx -t; then
    cp -a "$BACKUP" "$TARGET"
    nginx -t || true
    exit 1
fi
systemctl reload nginx
echo "Custom SSL installed for ${DOMAIN}"
openssl x509 -in "${FULLCHAIN}" -noout -enddate
