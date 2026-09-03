# Changelog

All notable changes to **Uplary** are documented here.

## [1.0.0] — 2026-08-17

### Initial CodeCanyon release

- Laravel 12 + Inertia.js + React 19 control plane
- Web installer at `/install` with super-admin bootstrap
- DigitalOcean provisioning and attach-by-IP for any Ubuntu VPS
- Laravel, WordPress, and React (Vite SPA) deployment pipelines
- Zero-downtime releases, rollbacks, Git webhooks, live deployment logs
- Let's Encrypt and custom SSL (Cloudflare Origin compatible)
- MySQL/PostgreSQL on-server databases with managed env sync
- Monitoring agent, alert rules, auto-heal, site uptime/DNS probes
- Firewall, security detection, remote file/config/terminal management
- Site and database backups with restore
- Stripe subscriptions, plans, quotas, feature flags
- Teams with roles and invitations
- Cloudflare DNS integration
- Super-admin panel: users, plans, branding, audit log, platform services
- Optional marketing site, blog, SEO, Google Auth, AI content tools
- In-app documentation from Markdown (`/docs`)
- REST API with Sanctum tokens
- 80+ PHPUnit feature tests

### Bug fixes in release candidate

- SSL auto-issuance after deploy no longer crashes when OpenSSL expiry output is invalid
- Deployment success is not rolled back when post-deploy SSL queue fails
- Unified product naming under **Uplary** (`config/uplary.php`, `uplary-*` server paths) with legacy `clouddeck-*` compatibility
- Release zip build scripts for CodeCanyon packaging (`scripts/build-release.ps1`, `scripts/build-release.sh`)
- Deployment log broadcasts tolerate missing timestamps
- Custom SSL expiry parsing validates `notAfter=` format before Carbon parse
- Managed database credentials enforced during deploy (`DatabaseBootstrap`, env sync)
- Horizon worker reload uses `horizon:terminate` after release switch
- Custom SSL OpenSSL 3 key/certificate pair validation

---

## Upgrade notes

1. Back up your database and `.env` before updating.
2. Run `composer install --no-dev`, `php artisan migrate --force`, and `npm run build`.
3. Run `php artisan horizon:terminate` to reload queue workers.
4. Clear caches: `php artisan config:cache`, `route:cache`, `view:cache`.
