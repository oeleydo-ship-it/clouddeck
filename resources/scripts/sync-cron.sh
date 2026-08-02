#!/usr/bin/env bash
set -Eeuo pipefail
ID={{ID}}
ENABLED={{ENABLED}}
EXPRESSION={{EXPRESSION}}
COMMAND={{COMMAND}}
FILE="/etc/cron.d/clouddeck-${ID}"
LOG="/var/log/clouddeck-cron-${ID}.log"
if [ "${ENABLED}" = "yes" ]; then
    printf 'SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n%s www-data %s >> %s 2>&1\n' "${EXPRESSION}" "${COMMAND}" "${LOG}" > "${FILE}"
    chmod 0644 "${FILE}"
    touch "${LOG}" && chown www-data:www-data "${LOG}"
else
    rm -f "${FILE}"
fi
systemctl reload cron
