#!/usr/bin/env bash
set -Eeuo pipefail
EXTENSION={{EXTENSION}}

retry_on_apt_lock() {
    local attempt=0
    local max_attempts=60
    local err
    err="$(mktemp)"
    while true; do
        if "$@" 2>"$err"; then
            cat "$err" >&2
            rm -f "$err"
            return 0
        fi
        if [ "$attempt" -lt "$max_attempts" ] && grep -qE "Could not get lock|Unable to acquire the dpkg frontend lock|Resource temporarily unavailable" "$err"; then
            echo "apt/dpkg is locked by another process, retrying in 5s (attempt $((attempt + 1))/${max_attempts})..." >&2
            attempt=$((attempt + 1))
            sleep 5
            continue
        fi
        cat "$err" >&2
        rm -f "$err"
        return 1
    done
}

# Installed for every PHP-FPM version actually present on this server, not a hardcoded list,
# so a site on any managed version picks it up without a mismatch.
versions=$(dpkg-query -W -f='${Package}\n' 'php*-fpm' 2>/dev/null | grep -oP '(?<=^php)\d+\.\d+(?=-fpm$)' | sort -u)
if [ -z "$versions" ]; then
    echo "No PHP-FPM versions are installed on this server" >&2
    exit 64
fi

installed_for=""
for version in $versions; do
    if retry_on_apt_lock apt-get install -y "php${version}-${EXTENSION}"; then
        systemctl reload "php${version}-fpm" 2>/dev/null || true
        installed_for="${installed_for} ${version}"
    fi
done

if [ -z "$installed_for" ]; then
    echo "Unable to install php-${EXTENSION} for any installed PHP version" >&2
    exit 65
fi

echo "Installed php-${EXTENSION} for PHP version(s):${installed_for}"
