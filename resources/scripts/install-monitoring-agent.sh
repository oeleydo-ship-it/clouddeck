#!/usr/bin/env bash
set -euo pipefail

printf '%s' {{AGENT_BASE64}} | base64 --decode > /usr/local/bin/uplary-monitor
chmod 0755 /usr/local/bin/uplary-monitor
umask 077
printf 'uplary_URL=%q\nuplary_SERVER_ID=%q\nuplary_MONITORING_SECRET=%q\n' {{APP_URL}} {{SERVER_ID}} {{SECRET}} > /etc/uplary-monitor.conf
cat > /etc/cron.d/uplary-monitor <<'CRON'
* * * * * root /usr/local/bin/uplary-monitor >> /var/log/uplary-monitor.log 2>&1
CRON
chmod 0644 /etc/cron.d/uplary-monitor
/usr/local/bin/uplary-monitor || true
