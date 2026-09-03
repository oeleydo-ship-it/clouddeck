# Firewall

Uplary can manage **UFW** rules on managed Ubuntu servers from the server **Firewall** tab.

## Default posture

Bootstrap typically allows:

- **22/tcp** — SSH
- **80/tcp** — HTTP
- **443/tcp** — HTTPS

Additional application ports (Reverb, custom APIs) must be opened explicitly if clients connect directly to the server IP.

## Managing rules

1. Open **Server → Firewall**.
2. Add rules by port/protocol and optional comment.
3. Uplary queues a sync job that applies UFW on the host.

Rules are idempotent: the remote script reconciles UFW to match the control-plane record.

## Cloudflare considerations

When Cloudflare proxies traffic, origin firewalls still need 80/443 from Cloudflare IP ranges (or the general internet if you accept broader exposure). SSH should remain restricted to your office IP or a bastion where possible.

## Security detection integration

[Security detection](security-detection.md) can automatically block offending IPs discovered by log scans. Manual UFW rules and automatic blocks coexist; review blocked IPs before removing legitimate crawlers.

## Troubleshooting

| Issue | Check |
|-------|-------|
| Cannot SSH after change | Provider console serial/VNC access; revert rules via provider firewall |
| Site unreachable | Confirm 80/443 allowed; nginx listening |
| Sync failed | Operation log on server; `ufw status verbose` over emergency SSH |
