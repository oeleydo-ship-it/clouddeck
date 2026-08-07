#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
PHP_VERSION={{PHP_VERSION}}
# Space-separated list of every version the control plane offers on site create.
# Installed alongside the default so an operator can pick 8.2–8.5 without a second apt pass.
PHP_VERSIONS={{PHP_VERSIONS}}

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

php_packages=()
for ver in ${PHP_VERSIONS}; do
    php_packages+=(
        "php${ver}-fpm" "php${ver}-cli" "php${ver}-mysql" "php${ver}-pgsql"
        "php${ver}-curl" "php${ver}-mbstring" "php${ver}-xml" "php${ver}-zip"
        "php${ver}-bcmath" "php${ver}-redis" "php${ver}-gd"
    )
done
apt_get install -y "${php_packages[@]}"

# Pin the system `php` CLI to the control-plane default so Composer and artisan match new sites.
if [ -x "/usr/bin/php${PHP_VERSION}" ]; then
    update-alternatives --set php "/usr/bin/php${PHP_VERSION}" 2>/dev/null || true
fi

curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt_get install -y nodejs
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

fpm_units=()
for ver in ${PHP_VERSIONS}; do
    fpm_units+=("php${ver}-fpm")
done
systemctl enable --now nginx redis-server supervisor fail2ban "${fpm_units[@]}"
mkdir -p /var/www && chown -R www-data:www-data /var/www
