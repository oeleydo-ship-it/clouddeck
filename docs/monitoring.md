# Monitoring and alerts

Uplary installs a small Bash collector on each managed Ubuntu host. The collector runs once per minute from `/etc/cron.d/Uplary-monitor`, records resource and service health, and submits a signed JSON sample to the control plane. Installation, rotation, and removal are queued remote operations and their output is retained with the server's other operation logs.

## Agent authentication

Enabling monitoring creates a cryptographically random 64-character secret. It is encrypted with the application key in the control-plane database, displayed once, and written to `/etc/Uplary-monitor.conf` with restrictive permissions. Rotating the secret immediately invalidates the previous agent signature and queues a configuration replacement. Disabling monitoring revokes the secret before remote removal is attempted.

The agent sends these headers:

- `X-Monitoring-Timestamp`: current Unix timestamp
- `X-Monitoring-Nonce`: 16 random bytes encoded as lowercase hexadecimal
- `X-Monitoring-Signature`: lowercase SHA-256 HMAC

The signed message is the exact concatenation `timestamp + "." + nonce + "." + raw JSON body`. Requests more than five minutes from control-plane time are rejected. A successfully authenticated nonce is cached for ten minutes and cannot be reused. Signatures are compared in constant time, and ingestion is rate-limited per route.

Keep host and control-plane clocks synchronized. Set `APP_URL` to the externally reachable HTTPS control-plane URL before installing agents.

## Collected data

Samples include CPU, memory, root-disk utilization, one-minute load average, cumulative network receive/transmit bytes, the five highest-CPU processes, and health for Nginx, PHP-FPM, MySQL, PostgreSQL, Redis, and Supervisor. The management screen displays the latest values, service state, and a 72-sample resource chart. `GET /api/metrics?server_id={uuid}&hours=24` returns tenant-scoped paginated history to Sanctum tokens with `servers:read`.

Metric timestamps are assigned by the control plane after signature validation rather than trusted from the host. This keeps ordering and retention predictable.

## Alert lifecycle

Rules support CPU, memory, disk, load, and offline duration. Resource rules can require one to twelve consecutive matching samples to reduce noise. When a rule first crosses its threshold, Uplary creates an open incident. Additional notifications are suppressed until the rule's cooldown expires. A normal sample resolves the incident automatically; the once-per-minute offline check resolves recovered heartbeat incidents.

Alert notifications are always written to the database. Email is sent when an enabled email channel exists. Enabled Slack and Discord webhooks and Telegram bot destinations are delivered by the `notifications` queue. Channel credentials are encrypted. Slack and Discord URLs are restricted to their official HTTPS hosts to prevent channels from becoming a server-side request forgery primitive.

## Auto-heal

Opt-in per server from the Monitoring tab. When enabled, each signed sample that reports Nginx, PHP-FPM, MySQL, Redis, or Supervisor as down is evaluated by `AutoHealServicesJob` on the `monitoring` queue. Healing requires the configured number of consecutive down samples (default 2), skips services still inside their cooldown (default 15 minutes), and skips when a pending or running operation of the same type already exists. Remediations reuse the allowlisted restart commands from server operations (`nginx:restart`, `php:restart`, `mysql:restart`, `redis:restart`, `supervisor:restart`) and are recorded as normal `ServerOperation` rows with an `auto-heal:{service}` target. PostgreSQL health is collected by the agent but is not auto-restarted until an allowlisted operation exists. Disabling monitoring also clears auto-heal state. Owners receive an `auto_heal` operational notification when a restart is queued.

## Site monitoring

Opt-in per site from the site Monitoring tab. When enabled, `DispatchSiteChecksJob` runs every minute on the `monitoring` queue and probes each active, monitored site whose server is ready and that is not mid-deploy.

- **Website down** — `CheckSiteUptimeJob` GETs `http(s)://{domain}{monitor_path}` (HTTPS when an active certificate exists). Status codes 200–399 count as up. After the configured consecutive failures (default 3), Uplary opens a `site_down` incident and notifies with cooldown (default 30 minutes). A successful probe resolves the incident and sends `site_recovered`.
- **DNS mismatch** — `CheckSiteDnsJob` resolves A/AAAA for the domain and compares them to the server public IP. Mismatches open a `dns_mismatch` incident with the same cooldown behavior.
- **Laravel queue health** — on minutes divisible by 15, Laravel sites also queue the existing `CheckSiteQueueHealthJob` (`queue_failed` notifications).

Resolved site monitor incidents follow the same retention as server alert incidents. Probe timeout defaults to `MONITORING_SITE_PROBE_TIMEOUT` (10 seconds).

## Retention and workers

`MONITORING_RETENTION_DAYS` defaults to 30. Resolved server and site incidents use `MONITORING_INCIDENT_RETENTION_DAYS`, which defaults to 180. A daily task deletes expired records. Open incidents are never pruned.

Production must run both Horizon and the Laravel scheduler. Horizon includes a dedicated `monitoring` supervisor for ingestion evaluation, offline checks, and site probes, while external channel delivery uses the `notifications` supervisor.

## Operational checks

- Ensure Redis backs both queues and cache so nonce replay protection works across application instances.
- Serve ingestion only over HTTPS.
- Restrict `/etc/Uplary-monitor.conf` to root and rotate credentials after suspected host compromise.
- Monitor agent logs at `/var/log/Uplary-monitor.log`.
- Verify `jq`, `curl`, `openssl`, `systemctl`, `top`, `free`, `df`, and `ps` are available on custom images.
