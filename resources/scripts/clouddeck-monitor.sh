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

# Measured from /proc/stat rather than parsed out of top. top's "%Cpu(s)" line has moved
# columns between procps versions ("0.0 id" vs "0.0%id"), and when the field lands
# somewhere else awk reads an empty idle figure and reports a flat 100% busy on a machine
# doing nothing. Two reads a second apart is the same arithmetic top does, without
# depending on how it prints.
read_cpu_totals() {
    awk '/^cpu / {idle=$5+$6; total=0; for (i=2; i<=NF; i++) total+=$i; print idle, total; exit}' /proc/stat
}
read -r cpu_idle_start cpu_total_start < <(read_cpu_totals)
sleep 1
read -r cpu_idle_end cpu_total_end < <(read_cpu_totals)
cpu=$(awk -v i0="$cpu_idle_start" -v t0="$cpu_total_start" -v i1="$cpu_idle_end" -v t1="$cpu_total_end" \
    'BEGIN {dt=t1-t0; di=i1-i0; if (dt <= 0) {print "0.00"} else {busy=(dt-di)*100/dt; if (busy<0) busy=0; if (busy>100) busy=100; printf "%.2f", busy}}')
read -r memory_total memory_used < <(free -b | awk '/^Mem:/ {print $2, $3}')
read -r disk_total disk_used disk_percent < <(df -B1 --output=size,used,pcent / | tail -1 | awk '{gsub(/%/,"",$3); print $1, $2, $3}')
load=$(cut -d' ' -f1 /proc/loadavg)
network_rx=$(awk '{sum += $1} END {print sum+0}' /sys/class/net/*/statistics/rx_bytes)
network_tx=$(awk '{sum += $1} END {print sum+0}' /sys/class/net/*/statistics/tx_bytes)
memory_percent=$(awk -v used="$memory_used" -v total="$memory_total" 'BEGIN {printf "%.2f", total ? used*100/total : 0}')
processes=$(ps -eo comm=,%cpu=,%mem= --sort=-%cpu | head -n 5 | jq -R -s 'split("\n") | map(select(length > 0) | capture("^(?<name>\\S+)\\s+(?<cpu>\\S+)\\s+(?<memory>\\S+)$") | {name, cpu:(.cpu|tonumber), memory:(.memory|tonumber)})')
services=$(jq -nc \
    --argjson nginx "$(systemctl is-active --quiet nginx && echo true || echo false)" \
    --argjson php_fpm "$( { systemctl is-active --quiet php8.5-fpm || systemctl is-active --quiet php8.4-fpm || systemctl is-active --quiet php8.3-fpm || systemctl is-active --quiet php8.2-fpm; } && echo true || echo false)" \
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
