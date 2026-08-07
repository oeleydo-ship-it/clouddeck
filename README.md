# Uplary

Uplary is a SaaS control plane (uplary.com) where developers connect their own cloud accounts or VPS, auto-provision Ubuntu servers, deploy Laravel and WordPress sites, and operate supporting services — without self-hosting the panel.

## Implemented

- Laravel 12, Sanctum, Livewire 3, Tailwind 4, Vite, Horizon
- Registration, login, logout, email verification, secure session rotation
- Customer/super-admin role foundation and server ownership policies
- UUID domain schema for cloud accounts, SSH keys, servers, sites, deployments, encrypted environment variables, metrics, and activity logs
- Encrypted DigitalOcean credentials and a provider-neutral `CloudProvider` contract
- DigitalOcean account validation, catalogs, droplet create/read/delete, and power/snapshot actions
- Queue-chain provisioning with persisted progress and failure state
- Hardened Ubuntu bootstrap for Nginx, PHP 8.5 (with 8.4/8.3/8.2), MySQL, Redis, Supervisor, Node 22, Composer, UFW, Fail2Ban, Certbot, and swap
- Sanctum server API, API Resources, validation scoped to resource ownership, rate-limited actions
- Responsive dark control-plane landing, auth, verification, and dashboard views
- TOTP two-factor login challenge with encrypted secrets and hashed, single-use recovery codes
- Password reset, profile/password editing, 90-day scoped API tokens, and session revocation
- Validated DigitalOcean connection management and encrypted provider tokens
- Generated Ed25519 and uploaded OpenSSH keys with tenant isolation and one-time private-key download
- Five-step Livewire provisioning wizard with live DigitalOcean regions, sizes, and Ubuntu images
- Site creation with queued Nginx virtual-host configuration
- Encrypted `.env` editor and shared persistent storage
- Release-based Laravel deployments with atomic `current` symlinks and five-release retention
- Composer/npm builds, migrations, Laravel caches, queue restarts, and service reloads
- Persisted live command output, deployment history, commit metadata, and rollback jobs
- HMAC-signed GitHub/Bitbucket and token-authenticated GitLab webhooks with branch and duplicate filtering
- Deployment REST resources plus queued database/email completion notifications
- MySQL and PostgreSQL database/user provisioning with encrypted credentials and one-time password display
- Private SQL import/export jobs with tenant-scoped backup downloads
- Let's Encrypt certificate issuance, forced HTTPS, expiry tracking, and scheduled renewal
- Cron and Supervisor worker management with validation, synchronization jobs, and persisted status
- Allowlisted Nginx, PHP-FPM, Supervisor, Redis, and MySQL service operations with audit output
- Responsive per-server operations console backed by a dedicated `operations` Horizon queue
- One-click Linux metric agent installation with encrypted per-server HMAC secrets and replay protection
- CPU, memory, disk, load, network, process, and service telemetry with 30-day retention
- Resource-history charts, dashboard aggregates, agent heartbeats, and tenant-scoped metric REST API
- Consecutive-sample and offline alert rules with cooldowns, incidents, and automatic recovery
- Opt-in auto-heal that restarts down Nginx, PHP-FPM, MySQL, Redis, and Supervisor services with consecutive-sample and cooldown guards
- Opt-in site monitoring for website-down HTTP probes, DNS mismatch checks, recovery notifications, and scheduled Laravel queue health
- Queued database/email, Slack, Discord, and Telegram alert delivery with encrypted channel configuration
- Versioned PHP-FPM pool and generated Nginx configuration with validation, backup, rollback, and service reload
- Queued site-root file manager for browsing, editing, upload/download, rename, permissions, ZIP, extraction, and deletion
- Audited browser command console restricted to allowlisted programs, relative paths, the site release, and the `www-data` user
- Streaming database exports and private remote transfers on local or S3-compatible filesystem disks
- Automatic expiration for prepared downloads and retained database exports
- Super-admin control center for customers, plan limits, feature flags, settings, billing reviews, and encrypted audit history
- Superadmin-gated staging sites on platform (`{slug}.staging.uplary.com`) or client domains, with promote-to-production deploy
- Subscription entitlement and quota enforcement across servers, sites, databases, API tokens, teams, and team members
- Provider-neutral billing activation contract with a transactional manual approval adapter
- Stable feature rollouts with plan/user override support and reusable route middleware
- Secure team invitations with hashed single-use tokens, role membership, and quota-aware seats
- Account suspension with immediate API token/session revocation and login/2FA blocking
- Timezone-aware daily, weekly, and monthly database or DigitalOcean snapshot policies
- Queue-driven recovery points with SHA-256 checksums, S3/private-disk storage, provider polling, and count-based retention
- Confirmation-gated database and full-server restores with durable restore history and audit records
- Super-admin switch for mandatory email verification with development-safe immediate verification
- Stripe-hosted subscription Checkout and Customer Portal with recurring plan Price mappings
- Signed, replay-safe queued Stripe webhooks that synchronize entitlements, invoices, tax totals, cancellations, and payment-failure notifications

## Local development

The product itself is hosted SaaS. Use this section only when developing or contributing to the control plane codebase.

Requirements: PHP 8.2+ (8.5 recommended), Composer 2, Node 20+, SQLite/MySQL, and Redis. Horizon runs on Linux because it requires `pcntl` and `posix`.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

For asynchronous provisioning, set `QUEUE_CONNECTION=redis`, configure Redis, and run:

```bash
php artisan horizon
php artisan schedule:work
```

Never use the synchronous queue in production. Configure mail before enabling customer registrations so verification links can be delivered.

## Architecture

Cloud integrations implement `App\Cloud\Contracts\CloudProvider`; provider selection is isolated in `CloudProviderManager`. Provisioning is initiated by an action and dispatched as a queue chain. Each idempotent stage reloads the server, records progress, and writes terminal failures. Remote execution is isolated behind `SshClient`; arbitrary customer terminal commands should be implemented through a separately sandboxed, audited command gateway.

## API

Issue tokens with Sanctum and send `Authorization: Bearer <token>`. Resources are exposed under `/api/servers`, `/api/sites`, `/api/deployments`, `/api/databases`, `/api/ssl`, and `/api/metrics`; `/api/profile` returns the authenticated user. Read and write access is constrained by the token's `servers:read` and `servers:write` abilities. Agent ingestion uses a separate signed protocol.

## Next phases

1. Team-owned infrastructure, granular team roles, resource transfer, and shared audit context.
2. Backup failure notifications, restore drills, encrypted off-site replication, and provider-managed database backups.
3. Interactive WebSocket PTY sessions, additional cloud providers, and multi-cloud orchestration.

## Verification

```bash
php artisan migrate:fresh --env=testing
php artisan test
npm run build
```

Do not provision real infrastructure until DigitalOcean API and SSH integration tests have been run against a dedicated non-production account.

See [Deployment engine](docs/deployment-engine.md) for releases, [Server operations](docs/server-operations.md) for managed services (databases, cron, workers, service actions, and [Firewall](docs/server-operations.md#firewall)), [Monitoring and alerts](docs/monitoring.md) for telemetry, [Remote management](docs/remote-management.md) for configuration, files, console commands, and object storage, [SaaS administration](docs/saas-administration.md) for plans and teams, [Automated backups](docs/backups.md) for recovery and retention, and [Stripe billing](docs/stripe-billing.md) for paid subscription operations.
