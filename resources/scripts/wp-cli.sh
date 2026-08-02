#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
ACTION={{ACTION}}
TARGET={{TARGET}}
SLUG={{SLUG}}
ROOT="/var/www/${DOMAIN}/current"

# Installed on demand rather than at bootstrap: a server may carry no WordPress site at
# all, and this keeps the two independent.
if [ ! -x /usr/local/bin/wp ]; then
    curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    mv /tmp/wp-cli.phar /usr/local/bin/wp
fi

# Everything runs as www-data so anything written lands with the ownership the web server
# needs, rather than root-owned files it cannot update later.
run_wp() { sudo -u www-data /usr/local/bin/wp --path="${ROOT}" --no-color "$@"; }

case "${ACTION}" in
    list)
        run_wp "${TARGET}" list --format=json --fields=name,title,status,version,update
        ;;
    install)
        run_wp "${TARGET}" install "${SLUG}" --activate
        ;;
    activate)
        run_wp "${TARGET}" activate "${SLUG}"
        ;;
    deactivate)
        # A theme cannot be deactivated, only replaced, so this is plugins only.
        run_wp plugin deactivate "${SLUG}"
        ;;
    delete)
        run_wp "${TARGET}" delete "${SLUG}"
        ;;
    update)
        run_wp "${TARGET}" update "${SLUG}"
        ;;
    *)
        echo "Unsupported action: ${ACTION}" >&2
        exit 1
        ;;
esac
