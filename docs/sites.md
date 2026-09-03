# Adding a site

Create sites from **Sites → Add site** once a server is **Ready**.

## Common fields

| Field | Notes |
|-------|-------|
| **Server** | Must be Ready; determines IP and installed stack |
| **Domain** | Primary hostname (e.g. `app.example.com`) |
| **Platform** | Laravel, WordPress, or React |
| **Repository / branch** | Required for Laravel and React |
| **PHP version** | Laravel and WordPress only (8.2–8.5) |
| **Web directory** | Usually `public` for Laravel; WordPress uses web root |

After creation, Uplary queues **ConfigureSiteJob** to write the Nginx vhost and directory layout under `/var/www/<domain>/`.

## Laravel sites

1. Enter the Git clone URL (HTTPS or SSH).
2. Choose PHP version and optional database attachment.
3. Set environment variables on the **Environment** tab (or sync from `.env.example` after first clone).
4. Deploy — see [Deployment engine](deployment-engine.md).

Features:

- Zero-downtime releases under `/var/www/<domain>/releases`
- Shared `.env` and `storage/` across releases
- Migrations, `config:cache`, queue workers, Horizon, Reverb hooks
- Git webhooks for automatic deploys

## React sites (Vite SPA)

1. Choose platform **React**.
2. Provide Git repository and branch — no PHP version or database required.
3. Add **`VITE_*`** keys on the Environment tab before building so Vite inlines them at compile time.
4. Deploy runs `npm ci`, `npm run build`, and configures Nginx to serve `current/dist` with SPA fallback to `index.html`.
5. If the build outputs to `build/` instead of `dist/`, the platform links it so the document root remains consistent.

React sites skip Laravel-specific steps (migrations, PHP-FPM pool tuning for app code, managed DB driver enforcement unless you add a backend separately).

## WordPress sites

1. Uplary downloads WordPress from wordpress.org into the site root.
2. **Attach a MySQL database** before or immediately after first deploy.
3. Deploy configures Nginx and PHP.
4. Visit `https://your-domain/wp-admin/install.php` to finish setup in the browser.

## Staging

When [staging sites](staging-sites.md) are enabled, link a staging domain on the same server and promote releases to production.

## DNS and SSL

- Point DNS to the server IP (or configure Cloudflare — see [DNS](dns.md)).
- Issue [SSL](ssl-certificates.md) after the vhost is active and HTTP answers on port 80 (or install custom origin certificates for Cloudflare full strict mode).

## Multi-tenant SaaS (talaldocs)

If you deploy a tenant SaaS product, see [talaldocs documentation](talaldocs/README.md) for subdomain and custom hostname setup.
