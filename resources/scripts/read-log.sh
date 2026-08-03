#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
SOURCE={{SOURCE}}
LINES={{LINES}}
PHP_VERSION={{PHP_VERSION}}

# The browser names a source, never a path. Anything else would let a log viewer read any
# file on the server the web user can reach.
case "${SOURCE}" in
    laravel)    CANDIDATES=("/var/www/${DOMAIN}/shared/storage/logs/laravel.log" "/var/www/${DOMAIN}/current/storage/logs/laravel.log") ;;
    nginx)      CANDIDATES=("/var/log/nginx/${DOMAIN}.error.log" "/var/log/nginx/error.log") ;;
    nginx-access) CANDIDATES=("/var/log/nginx/${DOMAIN}.access.log" "/var/log/nginx/access.log") ;;
    php)        CANDIDATES=("/var/log/php${PHP_VERSION}-fpm.log" "/var/log/php-fpm.log") ;;
    supervisor) CANDIDATES=("/var/log/supervisor/supervisord.log") ;;
    reverb)     CANDIDATES=(/var/log/clouddeck-worker-*.log) ;;
    redis)      CANDIDATES=("/var/log/redis/redis-server.log" "/var/log/redis/redis.log") ;;
    *)
        echo "Unsupported log source: ${SOURCE}" >&2
        exit 1
        ;;
esac

FOUND=""
for candidate in "${CANDIDATES[@]}"; do
    if [ -r "${candidate}" ]; then
        FOUND="${candidate}"
        break
    fi
done

if [ -z "${FOUND}" ]; then
    # Not an error worth failing the read over: a site that has never written a Laravel log,
    # or a server with no Reverb worker, simply has nothing here yet.
    echo "CLOUDDECK_LOG_PATH=none"
    echo "No ${SOURCE} log exists on this server yet. Looked for: ${CANDIDATES[*]}"
    exit 0
fi

echo "CLOUDDECK_LOG_PATH=${FOUND}"
tail -n "${LINES}" "${FOUND}"
