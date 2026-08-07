#!/usr/bin/env bash
# Idempotent software hardening for Uplary-provisioned Ubuntu hosts.
# Does NOT reset UFW or remove console-managed uplary-fw-* rules.
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive

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

echo "==> Ensuring baseline packages"
apt_get update -qq
apt_get install -y ufw fail2ban unattended-upgrades apt-listchanges needrestart

echo "==> UFW baseline (additive; preserves existing rules including uplary-fw-*)"
ufw default deny incoming || true
ufw default allow outgoing || true
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
if ! ufw status 2>/dev/null | grep -qi 'Status: active'; then
    ufw --force enable
fi
ufw status verbose || true

echo "==> SSH hardening (sshd drop-in; does not change ListenPort)"
mkdir -p /etc/ssh/sshd_config.d
cat >/etc/ssh/sshd_config.d/99-uplary-harden.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PermitRootLogin prohibit-password
X11Forwarding no
AllowAgentForwarding no
ClientAliveInterval 300
ClientAliveCountMax 2
MaxAuthTries 4
EOF
if sshd -t; then
    systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null || true
else
    echo "sshd config test failed; leaving previous SSH config in place" >&2
    rm -f /etc/ssh/sshd_config.d/99-uplary-harden.conf
fi

echo "==> Fail2Ban SSH jail"
mkdir -p /etc/fail2ban/jail.d
cat >/etc/fail2ban/jail.d/uplary-sshd.conf <<'EOF'
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = %(sshd_log)s
backend = %(sshd_backend)s
maxretry = 5
bantime = 1h
findtime = 10m
EOF
systemctl enable --now fail2ban
systemctl reload fail2ban 2>/dev/null || systemctl restart fail2ban

echo "==> Unattended security updates"
cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
if [ -f /etc/apt/apt.conf.d/50unattended-upgrades ]; then
    sed -i 's|^//\s*"\${distro_id}:\${distro_codename}-security";|"${distro_id}:${distro_codename}-security";|' /etc/apt/apt.conf.d/50unattended-upgrades || true
fi

echo "==> Kernel / network sysctl (additive drop-in)"
cat >/etc/sysctl.d/99-uplary-harden.conf <<'EOF'
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.default.accept_source_route = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv6.conf.default.accept_redirects = 0
kernel.kptr_restrict = 2
kernel.dmesg_restrict = 1
EOF
sysctl --system >/dev/null 2>&1 || sysctl -p /etc/sysctl.d/99-uplary-harden.conf || true

echo "==> Shared /tmp sticky bit"
chmod 1777 /tmp /var/tmp 2>/dev/null || true

echo "CLOUDDECK_HARDEN_OK=1"
echo "Software hardening complete."
