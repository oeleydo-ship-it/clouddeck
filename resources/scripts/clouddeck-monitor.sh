#!/usr/bin/env bash
set -euo pipefail

CONFIG_FILE="${CLOUDDECK_MONITOR_CONFIG:-/etc/clouddeck-monitor.conf}"
if [[ -f "$CONFIG_FILE" ]]; then
    # shellcheck disable=SC1090
    source "$CONFIG_FILE"
fi
: "${CLOUDDECK_URL:?Missing CLOUDDECK_URL}"
: "${CLOUDDECK_SERVER_ID:?Missing CLOUDDECK_SERVER_ID}"
: "${CLOUDDECK_MONITORING_SECRET:?Missing CLOUDDECK_MONITORING_SECRET}"

cpu=$(LC_ALL=C top -bn1 | awk '/Cpu\(s\)/ {print 100-$8; exit}')
read -r memory_total memory_used < <(free -b | awk '/^Mem:/ {print $2, $3}')
read -r disk_total disk_used disk_percent < <(df -B1 --output=size,used,pcent / | tail -1 | awk '{gsub(/%/,"",$3); print $1, $2, $3}')
load=$(cut -d' ' -f1 /proc/loadavg)
network_rx=$(awk '{sum += $1} END {print sum+0}' /sys/class/net/*/statistics/rx_bytes)
network_tx=$(awk '{sum += $1} END {print sum+0}' /sys/class/net/*/statistics/tx_bytes)
memory_percent=$(awk -v used="$memory_used" -v total="$memory_total" 'BEGIN {printf "%.2f", total ? used*100/total : 0}')
processes=$(ps -eo comm=,%cpu=,%mem= --sort=-%cpu | head -n 5 | jq -R -s 'split("\n") | map(select(length > 0) | capture("^(?<name>\\S+)\\s+(?<cpu>\\S+)\\s+(?<memory>\\S+)$") | {name, cpu:(.cpu|tonumber), memory:(.memory|tonumber)})')
services=$(jq -nc \
    --argjson nginx "$(systemctl is-active --quiet nginx && echo true || echo false)" \
    --argjson php_fpm "$(systemctl is-active --quiet php8.4-fpm && echo true || echo false)" \
    --argjson mysql "$(systemctl is-active --quiet mysql && echo true || echo false)" \
    --argjson postgresql "$(systemctl is-active --quiet postgresql && echo true || echo false)" \
    --argjson redis "$(systemctl is-active --quiet redis-server && echo true || echo false)" \
    --argjson supervisor "$(systemctl is-active --quiet supervisor && echo true || echo false)" \
    '{nginx:$nginx,php_fpm:$php_fpm,mysql:$mysql,postgresql:$postgresql,redis:$redis,supervisor:$supervisor}')

body=$(jq -nc \
    --argjson cpu "$cpu" --argjson memory "$memory_percent" --argjson disk "$disk_percent" --argjson load "$load" \
    --argjson memory_used "$memory_used" --argjson memory_total "$memory_total" \
    --argjson disk_used "$disk_used" --argjson disk_total "$disk_total" \
    --argjson network_rx "$network_rx" --argjson network_tx "$network_tx" \
    --argjson services "$services" --argjson processes "$processes" \
    '{cpu_percent:$cpu,memory_percent:$memory,disk_percent:$disk,load_average:$load,memory_used_bytes:$memory_used,memory_total_bytes:$memory_total,disk_used_bytes:$disk_used,disk_total_bytes:$disk_total,network_rx_bytes:$network_rx,network_tx_bytes:$network_tx,services:$services,processes:$processes}')
timestamp=$(date +%s)
nonce=$(openssl rand -hex 16)
signature=$(printf '%s' "$timestamp.$nonce.$body" | openssl dgst -sha256 -hmac "$CLOUDDECK_MONITORING_SECRET" -hex | awk '{print $2}')

curl --fail --silent --show-error --max-time 20 \
    -H 'Content-Type: application/json' \
    -H "X-Monitoring-Timestamp: $timestamp" \
    -H "X-Monitoring-Nonce: $nonce" \
    -H "X-Monitoring-Signature: $signature" \
    --data-binary "$body" \
    "$CLOUDDECK_URL/api/monitoring/$CLOUDDECK_SERVER_ID/metrics" >/dev/null
