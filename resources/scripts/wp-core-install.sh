#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
SITE_URL={{SITE_URL}}
TITLE={{TITLE}}
ADMIN_USER={{ADMIN_USER}}
ADMIN_EMAIL={{ADMIN_EMAIL}}
ADMIN_PASSWORD={{ADMIN_PASSWORD}}
ROOT="/var/www/${DOMAIN}/current"

# Kept out of wp-cli.sh so the credentials only ever travel with the one command that needs
# them, and so the other WP-CLI actions do not have to carry placeholders they never use.
if [ ! -x /usr/local/bin/wp ]; then
    curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    mv /tmp/wp-cli.phar /usr/local/bin/wp
fi

run_wp() { sudo -u www-data /usr/local/bin/wp --path="${ROOT}" --no-color "$@"; }

# Already installed is not a failure: the operator may simply have finished the wizard in the
# browser first, and reinstalling over a live site would destroy its content.
if run_wp core is-installed 2>/dev/null; then
    echo "WordPress is already installed; leaving it alone."
    exit 0
fi

# --skip-email: the address is the operator's own and the notification would be sent through
# the site's mail configuration, which has not been set up at this point.
run_wp core install \
    --url="${SITE_URL}" \
    --title="${TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_email="${ADMIN_EMAIL}" \
    --admin_password="${ADMIN_PASSWORD}" \
    --skip-email

run_wp option update blog_public 1
echo "CLOUDDECK_WP_INSTALLED=1"
