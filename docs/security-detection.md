# Security detection

Uplary security detection is a production-oriented detection and response layer for managed
servers and sites. It is intentionally conservative and does not replace an EDR or SIEM.

Customer-facing walkthrough: in-app **Support & documentation** → [Security detection](/docs#security-detection)
(sidebar **Security** at `/security`).

## Customer overview

- Open **Security** in the sidebar. Detection is enabled by default.
- Scans collect read-only host and site signals over managed SSH. Use **Scan now** /
  **Scan all now**, or rely on the five-minute schedule. Jobs run on the `operations` queue.
- Live per-server status: **Queued** → **Scanning…** → last completed scan (or **Failed**).
  The Security page polls status while a scan is in flight.
- **Detection settings** (team owners / admins): per-rule enable, threshold, window, and
  severity. **Reset to recommended defaults** clears workspace overrides. Keep detection on;
  tune only after observing a normal baseline.
- Incidents appear under **Notifications → Incidents** (filter Security). Acknowledge or
  resolve there. Email fires for **Security incident** when a recipient is subscribed (or to
  the account address when no recipients are configured).
- **Block IP** / **Unblock** are manual only (UFW deny via Firewall). Never automatic.
- Prerequisites: Ready server + managed SSH. App-level login/admin events need agent
  ingestion (`POST /api/monitoring/{server}/security-events`).

## Collection and prerequisites

- The scheduler dispatches scans every five minutes to the `operations` queue.
- Manual and scheduled scans persist per-server lifecycle status on `servers`
  (`security_scan_status`: idle/queued/running/succeeded/failed, plus
  `security_scan_message` and `security_scanned_at`). The Security page polls
  `GET /security/status` while any scan is queued or running. A status stuck on
  **Queued** longer than ten minutes is treated as stale (re-scan is allowed) —
  restart or start an `operations` worker (Platform Services → Queue workers, or
  `php artisan queue:work redis --queue=default,operations,deployments,provisioning,notifications,monitoring,billing`).
  Restart workers after deploying job code changes.
- A ready server needs a working managed SSH key. Collection is read-only except for the
  protected `/var/lib/uplary/security` integrity-hash baseline.
- The collector uses `journalctl` or `auth.log`, process and socket listings, Nginx access
  logs, and Fail2ban when available. Missing tools are skipped.
- `.env` contents, credentials, request bodies, cookies, and raw authentication log lines are
  never transmitted. Integrity events contain only a path and hash-change indication.
- The first integrity scan establishes a baseline without generating an alert.

## Detectors

Server rules cover repeated failed SSH logins, administrative user/group changes, known
crypto-miner process names, high-confidence mining ports, and changes to cron, SSH authorized
keys, `.env`, and web entry files.

Site rules cover brute-force login paths, POST bursts, one address rapidly scanning routes,
known scanner user agents, WAF/Fail2ban blocks, malware signatures, and unexpected admin
actions. Generic app-level events require an integration with
`POST /api/monitoring/{server}/security-events`. It uses the same timestamp, nonce, and HMAC
signature scheme as metric ingestion and accepts `auth_failed`, `admin_action`, `waf_block`,
`malware_signature`, and `file_changed`.

Security detection is enabled by default. Team owners and administrators configure it from
**Security → Detection settings** for the active team workspace; users without an active team
configure their personal resources. The Security page inventory (protected counts, managed
servers, and scan targets) uses the same accessibility scope as Servers and Firewall—
personal servers plus every team the user belongs to—so switching into a team workspace does
not hide personal inventory. Scheduled and agent-driven detection still resolve settings per
server (`team_id` → team settings, otherwise personal). Each detector can be enabled or
disabled and given a threshold, lookback window, and severity. Single-event rules retain a
fixed threshold of one. Settings are stored in `security_detection_settings`; safe defaults
remain in `config/security-detection.php` and are used whenever no database override exists.
**Reset to recommended defaults** removes the workspace overrides.

Keep detection enabled initially, observe normal activity, and only then tune noisy thresholds
or windows. Turning off the global workspace setting prevents scheduled collection, manual
scans, and incident creation from pushed agent events for that workspace.

Automatic IP blocking is off and is not configurable because there is no automatic mitigation
path. The UI labels response as **manual only** rather than implying that a non-functional
toggle offers protection.

## Incidents and response

Matching records are coalesced by detector, server/site, and source IP to avoid flooding.
Incidents appear under **Notifications → Incidents**, include sanitized evidence and an
occurrence count, and support open, acknowledged, resolved, and reopened states. New,
escalated, or cooldown-expired incidents create database/email notifications. State and
mitigation actions are written to the audit log.

**Block IP** is an explicit action. It accepts only a public source address, rejects private,
reserved, loopback, and the managed server's own address, and creates a normal UFW deny rule.
**Unblock IP** removes only the firewall rule generated for that incident. Uplary never deletes
users or kills processes automatically.

## Deployment

1. Run `php artisan migrate`.
2. Keep `php artisan schedule:run` active every minute.
3. Run queue workers for `operations`, `notifications`, and `monitoring` (site checks). Security scans use `operations`.
4. Visit **Security → Detection settings**, keep the recommended defaults for an initial scan,
   and tune thresholds only after observing normal traffic.
