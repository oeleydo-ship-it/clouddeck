#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
PHP_VERSION={{PHP_VERSION}}

# Fresh cloud images run their own background apt-get (cloud-init, unattended-upgrades) right
# after boot. Wait for cloud-init to finish, then retry any apt-get that still hits a held lock
# instead of failing the whole bootstrap on first contention.
command -v cloud-init >/dev/null 2>&1 && timeout 180 cloud-init status --wait >/dev/null 2>&1 || true

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

# Create swap before installing anything memory-hungry (mysql-server's postinstall briefly
# starts mysqld and gets OOM-killed on small Droplets without swap already in place).
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

apt_get update
apt_get -y upgrade
apt_get install -y nginx git curl jq zip unzip ufw fail2ban supervisor redis-server mysql-server postgresql postgresql-client certbot python3-certbot-nginx software-properties-common
retry_on_apt_lock add-apt-repository -y ppa:ondrej/php
apt_get update
apt_get install -y "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-pgsql" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-redis"
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt_get install -y nodejs
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now nginx "php${PHP_VERSION}-fpm" redis-server supervisor fail2ban
mkdir -p /var/www && chown -R www-data:www-data /var/www
