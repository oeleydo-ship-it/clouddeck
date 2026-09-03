# Provisioning (bring your own server)

**Provisioning** is the first-run bootstrap Uplary executes over SSH on a new or attached Ubuntu host.

## Triggers

- Creating a **DigitalOcean Droplet** from Uplary
- **Attaching an existing server by IP**
- Re-running bootstrap from server operations after a failed attempt

Jobs run on the `provisioning` queue with a long timeout (up to 30 minutes). Watch progress on the server detail page.

## What gets installed

Typical bootstrap includes:

- Nginx with site vhost scaffolding
- PHP-FPM (multiple versions where supported)
- Composer (for Laravel deploys)
- Node.js/npm (for asset and React builds)
- Redis
- Supervisor
- MySQL and/or PostgreSQL server packages (for on-server databases)
- Uplary monitoring agent and cron entry
- Firewall baseline (UFW) when enabled in settings

Exact packages depend on the image and platform version.

## Server status lifecycle

| Status | Meaning |
|--------|---------|
| **Pending** | Queued or waiting to start |
| **Provisioning** | Bootstrap script running |
| **Ready** | Host accepts site creation and deployments |
| **Failed** | Bootstrap error — inspect operation logs |

Site creation and the full server dashboard are available only when status is **Ready**.

## After provisioning

1. Confirm **Monitoring** shows recent samples.
2. Open **Firewall** if you need custom ports beyond HTTP/HTTPS/SSH.
3. **Add a site** and configure DNS.
4. Run the first **deploy** and **issue SSL**.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| Stuck in Provisioning | Operation log stderr; SSH connectivity; apt locks on host |
| Ready but deploy fails | Git access, disk space, PHP version mismatch |
| Monitoring offline | Clock skew, `/etc/Uplary-monitor.conf`, HTTPS reachability of control plane |

See [Server operations](server-operations.md) for allowlisted maintenance commands.
