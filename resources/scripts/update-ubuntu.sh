#!/usr/bin/env bash
# Safe package/security updates for Uplary Ubuntu hosts (not a distro release upgrade).
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive
NEEDRESTART_MODE="${NEEDRESTART_MODE:-a}"
export NEEDRESTART_MODE

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

apt_get() { retry_on_apt_lock apt-get "$@"; }

echo "==> apt-get update"
apt_get update

echo "==> apt-get upgrade -y"
apt_get -y upgrade

echo "==> apt-get autoremove -y"
apt_get -y autoremove

echo "CLOUDDECK_UPDATE_OK=1"
echo "Package updates complete. Reboot separately if a kernel update requires it."
