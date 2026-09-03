# What's new

Recent platform improvements worth knowing about when operating sites from the Uplary console.

## Inertia React console

The customer and admin interfaces run on **Inertia.js + React**. Live deployment logs, server operations, and site management use polling or Echo where appropriate instead of Livewire.

## React / Vite SPA deploys

Sites can be created with platform **React**. The deploy pipeline:

- Clones the Git repository
- Runs `npm ci` and `npm run build`
- Serves `current/dist` (or links `build/` when Vite outputs there)
- Configures Nginx with SPA fallback to `index.html`

Add `VITE_*` variables on the **Environment** tab before deploying so they are available at build time.

## SSL improvements

- **Let's Encrypt** can be queued automatically after site configuration and a successful deploy.
- **Custom SSL** accepts Cloudflare Origin certificates and other PEM bundles; validation compares certificate and private key pairs correctly on OpenSSL 3.
- **Remove SSL** returns the site to HTTP-only when you need to re-issue or switch certificate types.

## Managed database enforcement

When a site uses an attached **managed database**, `DB_*` environment variables are synced from the platform and cannot be overridden on the Environment tab. Deploy scripts validate the driver (MySQL or PostgreSQL) and refuse SQLite fallbacks when a managed driver is selected.

## Sites index and logs

The sites list uses a structured table (site, server/runtime, source/deploy, actions). The **Logs** tab preserves tab state on redirect and shows per-source log snapshots with inline errors.

## Cloudflare and SaaS custom hostnames

Super Admin DNS settings support Cloudflare zone integration, fallback origins, and auto-provisioning for multi-tenant products (for example talaldocs.com). See [DNS](dns.md) and [talaldocs admin guide](talaldocs/admin-cloudflare-setup.md).

## Local development ports

The control-plane `.env` defaults avoid colliding with other local apps:

| Service | Port |
|---------|------|
| Laravel | 8001 |
| Vite | 5174 |
| Reverb | 8082 |
| Redis | 6379 |

Adjust `APP_URL`, `VITE_*`, and Reverb settings if you change these.
