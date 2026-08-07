# Server operations

The server management screen is the operational control plane for databases, server-level cron entries, Supervisor workers, and service actions. TLS certificates and site cron entries are managed per site, on the site screen's SSL and Cron tabs. Every remote mutation is dispatched to the `operations` queue and records its status or command output before the request returns to the browser.

## Databases

Uplary provisions isolated MySQL or PostgreSQL databases and users through the server's privileged SSH connection. Generated passwords are encrypted in the control-plane database and shown to the customer only in the response that creates the database. When a database is attached to a site, its `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` environment entries are synchronized after remote creation succeeds.

Exports use `mysqldump` or `pg_dump` and are stored on the configured private filesystem disk. Downloads enforce database ownership. Imports accept SQL files up to 10 MB, store them privately, and stream their content to the remote command over SSH standard input. Larger production backups should use a future object-storage and streaming workflow instead of the current in-memory SSH transport.

## TLS certificates

Certificate jobs invoke Certbot's Nginx integration, optionally enable HTTPS redirects, and persist the certificate expiry reported by OpenSSL. The scheduler checks active, auto-renewing certificates daily at 02:15 and queues renewal when expiry is within 30 days.

The domain must already resolve to the server and ports 80 and 443 must be reachable. Wildcard certificates are intentionally unavailable until a provider-specific DNS-01 challenge integration is configured.

## Cron and queue workers

Cron entries are created either from a site's Cron tab, where they are bound to that site and its server, or from the server's Cron tab for entries that belong to no site. Cron expressions are validated as five fields and commands reject line breaks and shell-control characters before they are written to `/etc/cron.d`. Supervisor workers are generated from structured settings such as connection, queue, process count, retry count, timeout, and memory; customers cannot submit an arbitrary Supervisor configuration.

A Reverb worker binds to `127.0.0.1` on its allocated port and is published by Nginx on the site's own domain under `/app` and `/apps`, so the WebSocket reuses the site's certificate on port 443 and no high port is exposed. The proxy is written to `/etc/nginx/Uplary/<domain>-reverb.conf` and pulled in by a glob `include`, which survives both Certbot and the Nginx settings screen rewriting the vhost. The site's `REVERB_SCHEME` and `REVERB_PORT` follow whether the site has an active certificate, and are refreshed when one is issued; the compiled `VITE_REVERB_*` values reach the browser on the next deployment.

Deleting a cron entry or worker queues removal of its remote configuration. The UI status reflects the last synchronization job rather than assuming the remote action succeeded.

## Firewall

The Firewall console page (`/firewall`) manages per-server UFW rules for servers the customer owns or can operate through a team. It appears in the main sidebar directly below **Sites**. Select a ready server, then add, remove, apply, or refresh rules without pasting raw shell into the browser.

Bootstrap still opens OpenSSH and Nginx Full on new hosts. Rules created here layer on top of that baseline. Each managed rule is tagged on the remote host with a stable `uplary-fw-{id}` UFW comment so apply and remove stay idempotent.

### Requirements

- The target server status must be ready, with working SSH access from the control plane.
- UFW must be installed on the host (the Ubuntu bootstrap script includes it).
- Horizon (or an equivalent worker) must process the `operations` queue so sync and refresh jobs run.

### Rule fields

| Field | Values |
| --- | --- |
| Action | `allow` or `deny` |
| Protocol | `tcp`, `udp`, or `any` |
| Port / profile | Numeric port, or an allowlisted named profile: `OpenSSH`, `Nginx Full`, `Nginx HTTP`, `Nginx HTTPS` |
| From IP | Optional source IP or CIDR; empty means any source |
| Description | Optional operator note stored only in the control plane |

Customers cannot submit arbitrary UFW arguments or application profile names outside that list.

### Adding, applying, and deleting

1. Open **Firewall**, choose the server, and submit a new rule. Creation sets status to `pending` and dispatches `SyncFirewallRuleJob` on the `operations` queue.
2. **Apply to server** re-queues every stored rule for that host (useful after a failed sync or manual drift).
3. Deleting a rule soft-deletes it locally, marks it `pending`, and queues remote removal via the same sync job.

### Refresh remote status

**Refresh remote status** runs `ufw status verbose` through `RefreshFirewallStatusJob` and stores the summarized result on the server record. Use it to confirm what UFW currently reports without changing rules.

### Statuses

| Status | Meaning |
| --- | --- |
| `pending` | Queued or in flight; not yet confirmed on the host |
| `synced` | Last apply or remove succeeded |
| `failed` | Remote command failed; see `status_message` for detail |
| `missing_ufw` | UFW is not available on the host; the page surfaces this explicitly instead of failing silently |

The UI reflects the last synchronization job rather than assuming the remote action succeeded.

## Service actions

The application never accepts a raw service command. The operation job maps a fixed identifier to an allowlisted command for Nginx configuration testing/reload/restart, PHP-FPM reload/restart (every installed `php*-fpm` unit), and Supervisor, Redis, or MySQL restart. Each action is tenant-authorized and stores output, exit status, start time, and completion time.

## Maintenance (Services tab)

Three additional allowlisted operations run shell scripts from `resources/scripts/` over SSH and record output on the same `server_operations` history:

| Type | Script | Purpose |
| --- | --- | --- |
| `system:harden` | `harden-ubuntu.sh` | Idempotent hardening: ensure UFW/Fail2Ban/unattended-upgrades, additive UFW baseline (OpenSSH + Nginx Full only — does **not** `ufw reset` or remove `uplary-fw-*` console rules), SSH drop-in (password auth off), Fail2Ban sshd jail, sysctl drop-in |
| `system:update` | `update-ubuntu.sh` | `apt-get update && apt-get upgrade -y` (and autoremove) with apt-lock retries |
| `system:release-upgrade` | `upgrade-ubuntu-release.sh` | Noninteractive `do-release-upgrade`; requires typing the server hostname; disruptive and should be rare |

Package updates are the primary maintenance action. Major release upgrades are behind a confirmation panel with an explicit warning. Job timeout is 30 minutes on the `operations` queue.

## Production requirements

- Run Horizon with the `operations` supervisor enabled.
- Run `php artisan schedule:work` or invoke `php artisan schedule:run` every minute.
- Keep SSH private keys and provider credentials encrypted with a stable production `APP_KEY`.
- Configure a private filesystem disk and retention policy for database exports.
- Bootstrap hosts with the bundled Ubuntu script, or provide equivalent Nginx, PHP 8.5 (and 8.4/8.3/8.2), MySQL, PostgreSQL, Redis, Supervisor, Certbot, and firewall packages.
- Test provisioning against an isolated cloud account before enabling customer access.
