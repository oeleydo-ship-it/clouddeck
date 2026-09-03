# Uplary — Installation Guide

This guide covers installing **Uplary** (Laravel server control plane / PaaS panel) on your own VPS or shared hosting with SSH access.

---

## 1. Requirements

### Control plane server (where Uplary runs)

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| **OS** | Ubuntu 22.04 / 24.04 LTS | Ubuntu 24.04 LTS |
| **PHP** | 8.2 | 8.3+ |
| **Web server** | Nginx or Apache + PHP-FPM | Nginx |
| **Database** | MySQL 8, MariaDB 10.6+, PostgreSQL 14+, or SQLite (dev only) | MySQL 8 or PostgreSQL 16 |
| **Node.js** | 20.x | 22.x LTS |
| **Composer** | 2.x | Latest |
| **Redis** | 6+ | 7+ (required for production queues) |
| **RAM** | 2 GB | 4 GB+ |
| **Disk** | 10 GB | 20 GB+ SSD |

### PHP extensions

`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `zip`, `redis` (recommended), `intl` (recommended)

### Process requirements (production)

| Service | Purpose |
|---------|---------|
| **Horizon** | Queue workers (`deployments`, `provisioning`, `operations`, `monitoring`, `notifications`, `billing`) |
| **Scheduler** | Cron every minute → `php artisan schedule:run` |
| **Reverb** (optional) | Live deployment logs and realtime UI updates |
| **OpenSSH client** | Remote server management over SSH |

> **Note:** Laravel Horizon requires Linux with `pcntl` and `posix` extensions. It does not run on Windows.

### Managed application servers (customer VPS)

Ubuntu 22.04 or 24.04, root or sudo SSH access, ports 22/80/443 reachable from the control plane.

---

## 2. Quick install (development)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Visit `http://127.0.0.1:8000/install` and complete the web installer.

For local async jobs:

```bash
# Terminal 1
php artisan serve

# Terminal 2 (requires Redis)
QUEUE_CONNECTION=redis php artisan horizon

# Terminal 3
php artisan schedule:work
```

Or use the bundled dev script:

```bash
composer dev
```

---

## 3. Production installation

### Step 1 — Upload files

Upload the package to your server (e.g. `/var/www/uplary`) or clone from your purchase archive. Point the web root to `/public`.

```bash
cd /var/www/uplary
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### Step 2 — Configure `.env`

```env
APP_NAME=Uplary
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uplary
DB_USERNAME=uplary
DB_PASSWORD=strong-password

SESSION_DRIVER=database
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=support@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Realtime (optional but recommended)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=panel.yourdomain.com
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Generate Reverb credentials:

```bash
php artisan reverb:install
```

Build frontend assets on the server (or build locally and upload `public/build`):

```bash
npm ci
npm run build
```

### Step 3 — Database and permissions

```bash
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Step 4 — Web installer

Visit `https://panel.yourdomain.com/install` and create:

- Super administrator account (12+ character password)
- Platform name and support email
- Registration and email verification toggles
- Optional Stripe API keys (can be added later in Admin → Payments)

After installation, `/install` is disabled automatically when a user exists.

### Step 5 — Horizon (queue workers)

Create `/etc/supervisor/conf.d/uplary-horizon.conf`:

```ini
[program:uplary-horizon]
process_name=%(program_name)s
command=php /var/www/uplary/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/uplary/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start uplary-horizon
```

### Step 6 — Scheduler

Add to crontab for the `www-data` user (or root):

```cron
* * * * * cd /var/www/uplary && php artisan schedule:run >> /dev/null 2>&1
```

### Step 7 — Reverb (optional)

Supervisor program for WebSockets:

```ini
[program:uplary-reverb]
command=php /var/www/uplary/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/uplary/storage/logs/reverb.log
```

Proxy WebSocket traffic in Nginx:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
}
```

### Step 8 — Nginx site block (example)

```nginx
server {
    listen 443 ssl http2;
    server_name panel.yourdomain.com;
    root /var/www/uplary/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Issue TLS with Certbot or your preferred CA.

---

## 4. Post-install configuration

| Task | Where |
|------|-------|
| Branding (logo, favicon, name) | Admin → Settings → Branding |
| Mail (SMTP test) | Admin → Settings → Mail |
| Stripe billing | Admin → Payments + Stripe webhook `https://panel.yourdomain.com/stripe/webhook` |
| Plans and quotas | Admin → Plans |
| Feature flags | Admin → Feature flags |
| Google Sign-In | Admin → Google Auth |
| Object storage (S3 backups) | Admin → Storage |
| Cloudflare DNS integration | Admin → Settings → DNS |
| Horizon access | `.env` `HORIZON_ALLOWED_EMAILS` + super-admin role |

---

## 5. Stripe setup (optional)

1. Create products/prices in Stripe Dashboard for each paid plan.
2. Map Price IDs in **Admin → Plans**.
3. Add webhook endpoint: `POST /stripe/webhook`
4. Enable events: `checkout.session.completed`, `customer.subscription.*`, `invoice.*`
5. Paste webhook signing secret in Admin → Payments or `.env` `STRIPE_WEBHOOK_SECRET`

---

## 6. Connecting your first server

1. **Account → SSH keys** — generate or upload a public key.
2. **Servers → Add server** — connect DigitalOcean token or attach Ubuntu VPS by IP.
3. Wait for status **Ready**.
4. **Sites → Add site** — Laravel, WordPress, or React.
5. Point DNS A record to server IP.
6. Deploy and issue SSL from the site dashboard.

See the in-app documentation at `/docs` or the `docs/` folder for detailed guides.

---

## 7. Updating

```bash
cd /var/www/uplary
# Backup database and .env first
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan horizon:terminate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Troubleshooting

| Issue | Fix |
|-------|-----|
| `/install` redirect loop | Ensure migrations ran; database reachable |
| Jobs never run | Start Horizon; set `QUEUE_CONNECTION=redis` |
| Deployments stuck pending | Check Horizon logs; verify SSH from control plane to managed server |
| Live logs not updating | Configure Reverb + Nginx proxy; check `BROADCAST_CONNECTION=reverb` |
| 500 after update | Run `php artisan config:clear`; check `storage/logs/laravel.log` |
| Horizon forbidden | Add email to `HORIZON_ALLOWED_EMAILS` or use super-admin account |

---

## 9. Security checklist

- Set `APP_DEBUG=false` in production
- Restrict `/horizon` to trusted admins
- Use HTTPS everywhere
- Back up `APP_KEY` — losing it decrypts stored credentials
- Rotate provider API tokens and SSH keys periodically
- Keep PHP, Node, and Ubuntu packages updated

---

## 10. Support

Refer to `docs/getting-help.md` and the CodeCanyon item comments section for support channels provided by the author.
