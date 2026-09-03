# Uplary

**Uplary** is a self-hosted SaaS control plane for developers and agencies. Connect cloud accounts or VPS hosts, auto-provision Ubuntu servers, deploy **Laravel**, **WordPress**, and **React** sites, and operate SSL, databases, workers, monitoring, and backups from one dashboard.

Ideal for selling managed hosting, white-label PaaS, or internal DevOps tooling.

---

## Highlights

- **Multi-tenant SaaS** — plans, Stripe billing, quotas, feature flags, teams
- **Three app platforms** — Laravel (zero-downtime Git deploy), WordPress, React/Vite SPA
- **DigitalOcean API** + attach any Ubuntu VPS by IP (Contabo, Hetzner, etc.)
- **Full lifecycle** — provision, deploy, SSL, cron, queues, firewall, backups, monitoring
- **Super-admin panel** — users, plans, branding, audit log, platform services
- **Inertia + React** customer console with live deployment logs
- **Web installer** — no manual seeding required
- **80+ automated tests**

---

## Documentation

| Document | Description |
|----------|-------------|
| [INSTALLATION.md](INSTALLATION.md) | Server requirements, production setup, Horizon, Reverb, Stripe |
| [FEATURES.md](FEATURES.md) | Complete feature list for marketplace and sales pages |
| [CHANGELOG.md](CHANGELOG.md) | Version history |
| [CODECANYON.html](CODECANYON.html) | Marketplace item description (paste into CodeCanyon) |
| [docs/README.md](docs/README.md) | Operator and customer guides (also at `/docs` in the app) |

### Release packaging

```powershell
# Windows
powershell -ExecutionPolicy Bypass -File scripts/build-release.ps1 -Version 1.0.0
```

```bash
# Linux / macOS
bash scripts/build-release.sh 1.0.0
```

Output: `dist/uplary-{version}-{date}.zip` (excludes `vendor/`, `node_modules/`, `.env`, build artifacts).

---

## Quick start (local)

**Requirements:** PHP 8.2+, Composer 2, Node 20+, SQLite or MySQL, Redis (for Horizon)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/install` and create the administrator account.

For queues (requires Linux or WSL for Horizon):

```bash
QUEUE_CONNECTION=redis php artisan horizon
php artisan schedule:work
```

Or run everything together:

```bash
composer dev
```

---

## Production

See **[INSTALLATION.md](INSTALLATION.md)** for Nginx, Supervisor, Redis, Reverb, Stripe webhooks, and security hardening.

Minimum production stack:

1. PHP-FPM + Nginx → `/public`
2. MySQL or PostgreSQL
3. Redis + `php artisan horizon`
4. Cron → `php artisan schedule:run`
5. Optional Reverb for live logs

---

## Architecture

| Layer | Stack |
|-------|-------|
| Backend | Laravel 12, Sanctum, Horizon, Reverb |
| Frontend | Inertia.js 3, React 19, Tailwind CSS 4, Vite 6 |
| Remote ops | SSH (`SshClient`) + 36 provisioning/deploy scripts |

Cloud providers implement `App\Cloud\Contracts\CloudProvider`. Deployments use release directories with atomic symlink switching under `/var/www/<domain>/`.

---

## API

Issue a Sanctum token from **Account → API tokens**, then call:

```
Authorization: Bearer <token>
```

Resources: `/api/servers`, `/api/sites`, `/api/deployments`, `/api/databases`, `/api/ssl`, `/api/metrics`

---

## Verification

```bash
php artisan test
npm run build
```

---

## License

This item is licensed for use according to the Envato/CodeCanyon [Regular or Extended License](https://codecanyon.net/licenses/standard) purchased with the product. One license per deployed instance unless Extended License terms apply.

---

## Support

Use the CodeCanyon item comments/support tab for installation help. Include your PHP version, error logs from `storage/logs/laravel.log`, and steps to reproduce any issue.
