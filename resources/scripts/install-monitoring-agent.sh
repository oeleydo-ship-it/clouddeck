#!/usr/bin/env bash
set -euo pipefail

printf '%s' {{AGENT_BASE64}} | base64 --decode > /usr/local/bin/clouddeck-monitor
chmod 0755 /usr/local/bin/clouddeck-monitor
umask 077
printf 'CLOUDDECK_URL=%q\nCLOUDDECK_SERVER_ID=%q\nCLOUDDECK_MONITORING_SECRET=%q\n' {{APP_URL}} {{SERVER_ID}} {{SECRET}} > /etc/clouddeck-monitor.conf
cat > /etc/cron.d/clouddeck-monitor <<'CRON'
* * * * * root /usr/local/bin/clouddeck-monitor >> /var/log/clouddeck-monitor.log 2>&1
CRON
chmod 0644 /etc/cron.d/clouddeck-monitor
/usr/local/bin/clouddeck-monitor || true
