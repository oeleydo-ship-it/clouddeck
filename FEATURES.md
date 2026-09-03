# Uplary — Feature List

**Uplary** is a self-hosted SaaS control plane for provisioning Ubuntu servers and deploying **Laravel**, **WordPress**, and **React (Vite SPA)** applications — similar in scope to Laravel Forge or Cloudways, with built-in billing, teams, and monitoring.

---

## Platform overview

- **Laravel 12** backend with **Inertia.js + React 19** customer console
- **Multi-tenant SaaS** with subscription plans, quotas, and feature flags
- **Super-admin panel** for operators (users, plans, billing, branding, audit log)
- **Web installer** at `/install` for first-time setup
- **In-app documentation** rendered from Markdown at `/docs`
- **REST API** with Sanctum token scopes

---

## Authentication and accounts

- Email registration and login with secure session rotation
- Optional mandatory email verification (super-admin toggle)
- Password reset and profile management
- **TOTP two-factor authentication** with recovery codes
- **Google OAuth** Sign-In (admin-configurable)
- **API tokens** (90-day expiry, `servers:read` / `servers:write` scopes)
- Session revocation and account suspension
- Super-admin user impersonation (audited)

---

## Servers and infrastructure

### Cloud providers

- **DigitalOcean** — full API: validate token, list regions/sizes/images, create/delete/power Droplets, snapshots, import existing Droplets
- **Attach any Ubuntu VPS by IP** — Contabo, Hetzner, Linode, Vultr, AWS EC2, etc.
- Additional provider slots for credential storage (custom attach workflow)
- Optional **managed servers** — sell VPS sizes through Stripe checkout (operator-configured)

### Server bootstrap

Automated provisioning installs and configures:

- Nginx, PHP 8.2–8.5 (multi-version), PHP-FPM pools
- MySQL, PostgreSQL, Redis, Supervisor
- Node.js 22, Composer, Certbot
- UFW firewall baseline, Fail2Ban, swap
- Monitoring agent (HMAC-signed telemetry)

### Server management

- Real-time provisioning progress and operation logs
- **phpMyAdmin** install/remove per server
- **PHP extension** installation across all PHP versions
- Allowlisted service operations (Nginx, PHP-FPM, MySQL, Redis, Supervisor restart)
- Firewall rule management synced to UFW
- Provider OS snapshot backups (DigitalOcean)

---

## Sites and deployments

### Supported platforms

| Platform | Highlights |
|----------|------------|
| **Laravel** | Git deploy, Composer/npm, migrations, zero-downtime releases, Horizon/Reverb hooks |
| **WordPress** | wordpress.org download, wp-config generation, plugin/theme management via WP-CLI |
| **React** | Vite SPA build (`npm ci` / `npm run build`), Nginx `index.html` fallback |

### Deployment engine

- Release-based layout (`/var/www/<domain>/releases` + atomic `current` symlink)
- Five-release retention with one-click rollback
- Live deployment logs (polling + optional Reverb WebSockets)
- Git webhooks: GitHub/Bitbucket HMAC, GitLab token, custom HMAC header
- Encrypted environment variable editor
- Custom deploy scripts
- Managed Laravel packages (Horizon, Reverb) via UI
- Cancel/retry deployments; duplicate commit filtering

### SSL/TLS

- **Let's Encrypt** issuance, renewal, and forced HTTPS
- **Custom PEM upload** (Cloudflare Origin certificates, corporate CA)
- Auto-queue SSL after successful deploy
- Remove SSL / return to HTTP

### Staging

- Linked staging sites on same server
- Promote staging release to production

---

## Databases

- On-server **MySQL** and **PostgreSQL** provisioning
- Encrypted credential storage with one-time password reveal
- Attach database to site — platform syncs `DB_*` environment (cannot be overridden manually)
- SQL import/export jobs with tenant-scoped downloads
- Scheduled database backup policies with restore

---

## Workers and scheduling

- Per-site Supervisor queue workers (create, monitor, destroy)
- Server-level and site-level cron jobs with sync to crontab
- Laravel scheduler integration
- Queue health checks for Laravel sites

---

## DNS

- **Cloudflare** integration: connect account, import zones, CRUD DNS records
- Platform staging DNS (auto A records on operator zone)
- SaaS custom hostname support (SSL for SaaS / fallback origin configuration)

---

## Monitoring and alerts

- Metric agent: CPU, memory, disk, load, network, top processes, service health
- 30-day metric retention with charts and REST API
- Alert rules with consecutive-sample thresholds and cooldowns
- Incidents dashboard with email, Slack, Discord, Telegram channels
- **Auto-heal** — optional automatic service restarts
- **Site monitoring** — HTTP down, DNS mismatch, Laravel queue health probes

---

## Security

- Security signal collection from managed servers
- IP block/unblock from incidents
- Audited terminal console (allowlisted commands, `www-data` user, site scope)
- Encrypted storage for tokens, SSH keys, environment secrets, 2FA secrets

---

## Remote management (plan-gated)

- Nginx and PHP-FPM configuration editor with validation, backup, rollback
- File manager: browse, edit, upload, download, ZIP, permissions, delete
- Audited command terminal restricted to safe programs and paths

---

## Backups

- Full site backups (code + database) with restore
- Scheduled database export policies with retention
- OS-level provider snapshots with restore history
- S3-compatible off-site storage support

---

## Billing and plans

- Free / Pro / Business plan tiers with configurable quotas
- Quotas: servers, managed servers, sites, databases, API tokens, teams, members, backup storage
- **Stripe Checkout** and Customer Portal
- Webhook-driven subscription sync, invoices, tax, payment failure notifications
- Manual plan-change requests with admin approval workflow
- Feature flags with rollout percentage and per-plan/per-user overrides

---

## Teams

- Team workspaces with owner, operator, and viewer roles
- Email invitations with hashed single-use tokens
- Shared server and site access within team scope

---

## Marketing site (optional)

- Public landing pages: home, about, features, use cases, contact, blog
- SEO: sitemap, robots.txt, meta tags
- Google Analytics, webmaster verification, custom insert code
- AI blog post generator (admin-configured)
- AI platform guide chat widget

---

## Admin panel

- Dashboard with platform metrics
- User management: suspend, role, subscription assignment, impersonate
- Plans CRUD with Stripe price mapping
- Feature flags, billing review queue
- Branding, mail, storage, notifications, managed server pricing
- Platform services: start/stop Redis, Reverb, Horizon from UI
- Audit log with encrypted sensitive fields
- Blog CMS

---

## API endpoints (Sanctum)

- `/api/servers`, `/api/sites`, `/api/deployments`
- `/api/databases`, `/api/ssl`, `/api/metrics`, `/api/profile`
- Agent ingestion endpoints for monitoring and security events
- Stripe webhook receiver

---

## Technical highlights

- 80+ automated feature tests
- Queue isolation: `deployments`, `provisioning`, `operations`, `monitoring`, `notifications`, `billing`
- SSH scripts passed via stdin (secrets not in process arguments)
- Doctrine DBAL for schema introspection
- Ziggy route helpers for React frontend

---

## What is not included

- Mobile native apps
- Fully automated multi-cloud orchestration beyond DigitalOcean API
- Interactive WebSocket PTY terminal (allowlisted commands only)
- Managed database hosting external to customer VPS (databases run on the same server as the app)

---

For operator guides, see the `docs/` directory or the in-app help center at `/docs`.
