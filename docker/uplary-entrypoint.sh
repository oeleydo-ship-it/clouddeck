#!/bin/sh
# Uplary container bootstrap — used by UIDesired / Docker deployments.
set -eu

APP_ROOT="${APP_ROOT:-/var/www/html}"
CADDY_DIR="/etc/caddy"
CADDYFILE="${CADDY_DIR}/Caddyfile"
APP_PORT="${APP_PORT:-8080}"
APP_DOMAIN="${APP_DOMAIN:-localhost}"

log() {
    printf '%s\n' "$*"
}

ensure_caddy_layout() {
    mkdir -p "${CADDY_DIR}" /var/log/caddy /data/caddy /config/caddy
    chown -R caddy:caddy /var/log/caddy /data/caddy /config/caddy 2>/dev/null || true
}

write_caddyfile() {
    # UIDesired routes public traffic through an edge proxy; the app only needs
    # to listen internally on APP_PORT.
    cat > "${CADDYFILE}" <<EOF
{
    auto_https off
    admin off
}

:${APP_PORT} {
    root * ${APP_ROOT}/public
    encode gzip zstd

    @reverb path /app*
    handle @reverb {
        reverse_proxy 127.0.0.1:${REVERB_SERVER_PORT:-8082}
    }

    php_fastcgi unix//var/run/php/php8.3-fpm.sock
    file_server
    try_files {path} {path}/ /index.php?{query}
}
EOF
}

prepare_laravel() {
    cd "${APP_ROOT}"

    if [ ! -f .env ] && [ -f .env.example ]; then
        cp .env.example .env
    fi

    if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:" ]; then
        php artisan key:generate --force --no-interaction
    fi

    php artisan migrate --force --no-interaction || log "WARN: migrate failed (database may not be ready yet)"

    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan view:cache --no-interaction || true
    php artisan storage:link --force --no-interaction 2>/dev/null || true
}

log "Ensuring Caddy directories exist..."
ensure_caddy_layout

log "Writing ${CADDYFILE} for ${APP_DOMAIN} on port ${APP_PORT}..."
write_caddyfile

log "Preparing Laravel application..."
prepare_laravel

log "Starting supervisord (Caddy, PHP-FPM, Horizon, Reverb, scheduler)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/uplary.conf
