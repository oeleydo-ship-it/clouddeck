#!/usr/bin/env bash
set -Eeuo pipefail
ID={{ID}}
ENABLED={{ENABLED}}
DOMAIN={{DOMAIN}}
NAME={{NAME}}
TYPE={{TYPE}}
CONNECTION={{CONNECTION}}
QUEUE={{QUEUE}}
PROCESSES={{PROCESSES}}
TRIES={{TRIES}}
TIMEOUT={{TIMEOUT}}
MEMORY={{MEMORY}}
PORT={{PORT}}
# Canonical Supervisor program name matches production workers (clouddeck-{id}).
# Legacy Uplary-{id} programs are stopped/removed on sync so status and deploy stay aligned.
PROGRAM="clouddeck-${ID}"
LEGACY_PROGRAM="Uplary-${ID}"
FILE="/etc/supervisor/conf.d/${PROGRAM}.conf"
LEGACY_FILE="/etc/supervisor/conf.d/${LEGACY_PROGRAM}.conf"
PROXY="/etc/nginx/clouddeck/${DOMAIN}-reverb.conf"
VHOST="/etc/nginx/sites-available/${DOMAIN}"

# Reverb is proxied by Nginx on the site's own domain, so the WebSocket shares the site's
# TLS certificate and port 443 instead of needing a public high port. The proxy lives in its
# own include file: Certbot and the Nginx settings screen both rewrite the vhost, and a glob
# include (note the trailing *) survives that and tolerates the file not existing yet.
reload_nginx() {
    if nginx -t; then
        systemctl reload nginx
    else
        echo 'Nginx rejected the Reverb proxy configuration' >&2
        return 1
    fi
}

link_proxy_include() {
    [ -f "${VHOST}" ] || return 0
    grep -q "clouddeck/${DOMAIN}-reverb.conf" "${VHOST}" && return 0
    cp -a "${VHOST}" "${VHOST}.clouddeck-bak"
    # Ahead of the first "location /" so the WebSocket routes win over the PHP handler.
    if ! sed -i "0,/^[[:space:]]*location \/ /s||    include /etc/nginx/clouddeck/${DOMAIN}-reverb.conf*;\n&|" "${VHOST}"; then
        cp -a "${VHOST}.clouddeck-bak" "${VHOST}"
        return 1
    fi
    if ! grep -q "clouddeck/${DOMAIN}-reverb.conf" "${VHOST}"; then
        echo "Could not find a location block in ${VHOST} to attach the Reverb proxy to" >&2
        cp -a "${VHOST}.clouddeck-bak" "${VHOST}"
        return 1
    fi
}

if [ "${ENABLED}" = "yes" ]; then
    case "${TYPE}" in
        horizon)
            ARGS="horizon"
            NUMPROCS=1
            ;;
        reverb)
            ARGS="reverb:start --host=127.0.0.1 --port=${PORT}"
            NUMPROCS=1
            mkdir -p /etc/nginx/clouddeck
cat > "${PROXY}" <<PROXYCONF
location ~ ^/(app|apps)(/|\$) {
    proxy_pass http://127.0.0.1:${PORT};
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Upgrade \$http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
PROXYCONF
            link_proxy_include
            reload_nginx
            # The port is reachable only over the proxy now; drop any rule an older
            # CloudDeck release opened for it.
            ufw delete allow "${PORT}/tcp" || true
            ;;
        *)
            ARGS="queue:work ${CONNECTION} --queue=${QUEUE} --sleep=3 --tries=${TRIES} --timeout=${TIMEOUT} --memory=${MEMORY}"
            NUMPROCS=${PROCESSES}
            ;;
    esac

# Drop any leftover legacy program so we never run two supervisors for one worker.
supervisorctl stop "${LEGACY_PROGRAM}:*" || true
rm -f "${LEGACY_FILE}"

cat > "${FILE}" <<CONF
[program:${PROGRAM}]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/${DOMAIN}/current/artisan ${ARGS}
directory=/var/www/${DOMAIN}/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=${NUMPROCS}
redirect_stderr=true
stdout_logfile=/var/log/clouddeck-worker-${ID}.log
stopwaitsecs=$((TIMEOUT + 30))
CONF
else
    supervisorctl stop "${PROGRAM}:*" || true
    supervisorctl stop "${LEGACY_PROGRAM}:*" || true
    rm -f "${FILE}" "${LEGACY_FILE}"
    if [ "${TYPE}" = "reverb" ]; then
        # Leaving the glob include in the vhost is harmless once the file is gone.
        rm -f "${PROXY}"
        reload_nginx || true
        if [ -n "${PORT}" ]; then
            ufw delete allow "${PORT}/tcp" || true
        fi
    fi
fi
supervisorctl reread
supervisorctl update
