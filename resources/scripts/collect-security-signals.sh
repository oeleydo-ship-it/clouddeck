#!/usr/bin/env bash
set -uo pipefail

WINDOW_MINUTES="${WINDOW_MINUTES:-5}"
STATE_DIR="/var/lib/uplary/security"
BASELINE="$STATE_DIR/integrity.sha256"
TMP_BASELINE="$(mktemp)"
trap 'rm -f "$TMP_BASELINE"' EXIT

json_event() {
    local key="$1" source="$2" ip="$3" count="$4" summary="$5" evidence_key="${6:-detail}" evidence_value="${7:-}" domain="${8:-}"
    printf '{"detector_key":"%s","source":"%s","source_ip":"%s","domain":"%s","count":%s,"summary":"%s","evidence":{"%s":"%s"}}\n' \
        "$key" "$source" "$ip" "$domain" "$count" "$summary" "$evidence_key" "$evidence_value"
}

# Authentication sources vary by distro. Only aggregate source addresses; never return raw log lines.
AUTH_DATA=""
if command -v journalctl >/dev/null 2>&1; then
    AUTH_DATA="$(journalctl --since "$WINDOW_MINUTES minutes ago" -u ssh -u sshd -u sudo --no-pager 2>/dev/null || true)"
elif [ -r /var/log/auth.log ]; then
    AUTH_DATA="$(tail -n 5000 /var/log/auth.log 2>/dev/null || true)"
fi

printf '%s\n' "$AUTH_DATA" | awk '/Failed password|authentication failure/ {for(i=1;i<=NF;i++) if($i=="from") print $(i+1)}' \
    | sort | uniq -c | while read -r count ip; do
        [ -n "$ip" ] && json_event "ssh.failed_logins" "auth" "$ip" "$count" "Repeated failed SSH authentication" "failed_attempts" "$count"
    done

admin_count="$(printf '%s\n' "$AUTH_DATA" | grep -Eic 'user(add|mod).*sudo|new user.*(sudo|admin)|adduser.*sudo' || true)"
if command -v ausearch >/dev/null 2>&1; then
    audit_admin_count="$(ausearch -ts recent -m EXECVE 2>/dev/null | grep -Eic 'useradd|usermod.*sudo|adduser.*sudo' || true)"
    admin_count=$((admin_count + audit_admin_count))
fi
if [ "$admin_count" -gt 0 ]; then
    json_event "privilege.admin_user_created" "sudo" "" "$admin_count" "Administrative account or group membership changed" "events" "$admin_count"
fi

# Process checks require both a known miner name or sustained very high CPU; evidence is name/CPU only.
ps -eo comm=,pcpu= 2>/dev/null | awk 'BEGIN{IGNORECASE=1} /xmrig|minerd|cpuminer|ethminer|kdevtmpfsi|kinsing/ {print $1, $2}' \
    | while read -r process cpu; do
        json_event "process.crypto_miner" "process" "" "1" "Known mining process signature is running" "process" "${process}:${cpu}%"
    done

if command -v ss >/dev/null 2>&1; then
    ss -Hntp state established 2>/dev/null | awk '$5 ~ /:(3333|4444|5555|7777|14444)$/ {print $5}' \
        | sort -u | while read -r destination; do
            json_event "network.suspicious_outbound" "network" "" "1" "Connection to a commonly abused mining port" "destination" "$destination"
        done
fi

# Hash only. The initial run establishes a baseline and deliberately emits no incident.
mkdir -p "$STATE_DIR" 2>/dev/null || true
chmod 700 "$STATE_DIR" 2>/dev/null || true
for path in /etc/crontab /etc/cron.d /root/.ssh/authorized_keys /home/*/.ssh/authorized_keys /var/www/*/.env /var/www/*/public/index.php; do
    [ -e "$path" ] || continue
    if [ -d "$path" ]; then
        find "$path" -maxdepth 2 -type f -print0 2>/dev/null | sort -z | xargs -0 sha256sum 2>/dev/null
    elif [ -r "$path" ]; then
        sha256sum "$path" 2>/dev/null
    fi
done | sort > "$TMP_BASELINE"

if [ -s "$BASELINE" ]; then
    comm -3 "$BASELINE" "$TMP_BASELINE" | awk '{print $2}' | sort -u | while read -r path; do
        [ -n "$path" ] && json_event "integrity.critical_file_changed" "integrity" "" "1" "Critical file hash changed" "path" "$path"
    done
fi
install -m 600 "$TMP_BASELINE" "$BASELINE" 2>/dev/null || true

# Nginx combined logs: aggregate only IP/domain/count and never transmit request bodies or cookies.
for log in /var/log/nginx/*access*.log; do
    [ -r "$log" ] || continue
    domain="$(basename "$log" | sed -E 's/[-_.]?access([._-].*)?\.log$//; s/^access$//')"
    tail -n 10000 "$log" 2>/dev/null | awk '
{
    ip=$1; request=$6 " " $7; ua=tolower($0);
    if (request ~ /"POST /) posts[ip]++;
    if ($7 ~ /(login|wp-login|xmlrpc|signin)/ && request ~ /"POST /) logins[ip]++;
    routes[ip SUBSEP $7]=1;
    if (ua ~ /(sqlmap|nikto|masscan|nmap|acunetix|nessus|zgrab)/) bad[ip]++;
}
END {
    for (ip in posts) printf "web.post_burst|%s|%d\n", ip, posts[ip];
    for (ip in logins) printf "web.bruteforce|%s|%d\n", ip, logins[ip];
    for (pair in routes) {split(pair,p,SUBSEP); unique[p[1]]++}
    for (ip in unique) printf "web.route_scan|%s|%d\n", ip, unique[ip];
    for (ip in bad) printf "web.bad_user_agent|%s|%d\n", ip, bad[ip];
}' | while IFS='|' read -r key ip count; do
        json_event "$key" "nginx" "$ip" "$count" "Suspicious web traffic threshold candidate" "requests" "$count" "$domain"
    done
done

# Optional security tools are consumed conservatively when present.
if command -v fail2ban-client >/dev/null 2>&1; then
    fail2ban-client status 2>/dev/null | grep -Eo 'Jail list:.*' | grep -Eo '[A-Za-z0-9_-]+' | while read -r jail; do
        fail2ban-client status "$jail" 2>/dev/null | awk -F: '/Currently banned/ {gsub(/ /,"",$2); print $2}' | while read -r count; do
            [ "${count:-0}" -gt 0 ] && json_event "waf.blocked" "fail2ban" "" "$count" "Fail2ban currently has blocked sources" "jail" "$jail"
        done
    done
fi

waf_count="$(tail -n 10000 /var/log/nginx/*error*.log 2>/dev/null | grep -Eic 'ModSecurity.*(denied|blocked)|access denied by rule' || true)"
if [ "$waf_count" -gt 0 ]; then
    json_event "waf.blocked" "waf" "" "$waf_count" "Web application firewall blocked requests" "blocked_requests" "$waf_count"
fi

if [ -r /var/log/ufw.log ]; then
    tail -n 5000 /var/log/ufw.log 2>/dev/null | awk '/UFW BLOCK/ {for(i=1;i<=NF;i++) if($i ~ /^SRC=/) {sub(/^SRC=/,"",$i); print $i}}' \
        | sort | uniq -c | while read -r count ip; do
            json_event "waf.blocked" "firewall" "$ip" "$count" "Repeated host firewall blocks" "blocked_packets" "$count"
        done
fi
