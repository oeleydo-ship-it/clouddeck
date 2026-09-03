# Docker deployment (UIDesired / self-hosted)

Image: **`jhay555/uidesired:uplary`**

## Architecture (single container)

| Port | Service |
|------|---------|
| **8080** | Caddy (UIDesired proxy targets this) |
| **8000** | Laravel API (`php artisan serve`) |
| **8081** | Reverb WebSockets |
| **3000** | Tenant site renderer (Node) |
| **6379** | Redis (inside container) |

Caddy routes:

- `*.PLATFORM_DOMAIN` → renderer `:3000` (customer published sites)
- `/api*`, `/sanctum*`, `/storage*`, `/broadcasting*` → Laravel `:8000`
- `/app/*` → Reverb `:8081`
- Everything else → static admin dashboard (`/srv/dashboard`)

---

## Fix: Bad Gateway on `app.aidirectory24.com`

### 1. Pull the latest image (Caddy directory fix)

Your first deploy failed on an older layer **without** `mkdir -p /etc/caddy`. The current Hub image includes it. On the server:

```bash
docker pull jhay555/uidesired:uplary
```

Then **recreate** the container from UIDesired (not just restart).

### 2. Add missing Reverb / broadcast env vars (main cause of 502 now)

If `REVERB_APP_KEY` is empty, **Laravel crashes on boot** and nothing listens on `:8000`. Caddy returns **502 Bad Gateway**.

Add these in UIDesired **Environment** (generate your own secrets for production):

```env
APP_URL=https://app.aidirectory24.com
APP_KEY=base64:YOUR_32_BYTE_KEY_HERE

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=900662
REVERB_APP_KEY=mcinvxd65ysjex2lek03
REVERB_APP_SECRET=yyrb4fbxshiffusgmkou
REVERB_HOST=app.aidirectory24.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_PORT=8081

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
```

Generate `APP_KEY` locally: `php artisan key:generate --show`

**Quick smoke test:** set `BROADCAST_CONNECTION=log` instead of `reverb` if you only need the panel up first.

### 3. Database (MariaDB sidecar)

```env
DB_CONNECTION=mysql
DB_HOST=test1-omddw-db
DB_PORT=3306
DB_DATABASE=uplary
DB_USERNAME=uplary
DB_PASSWORD=your_password
```

Host must match the **sidecar container name** on the Docker network.

### 4. PLATFORM_DOMAIN vs admin hostname

If `PLATFORM_DOMAIN=aidirectory24.com`, then **`app.aidirectory24.com` matches `*.aidirectory24.com`** and Caddy sends traffic to the **tenant renderer** (`:3000`), not the admin dashboard.

For admin on `app.aidirectory24.com`, use a **different** publish domain, for example:

```env
PLATFORM_DOMAIN=sites.aidirectory24.com
PREVIEW_DOMAIN=preview.sites.aidirectory24.com
CLOUDFLARE_FALLBACK_ORIGIN=fallback.sites.aidirectory24.com
```

Customer sites: `*.sites.aidirectory24.com`  
Admin panel: `app.aidirectory24.com` → dashboard SPA

---

## Verify after redeploy

SSH to the server (`164.92.210.14`):

```bash
docker ps -a | grep test1-omddw
docker logs test1-omddw --tail 80
docker exec test1-omddw supervisorctl status
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/
```

Expected:

- Container **Up** (not Restarting)
- `api`, `caddy`, `redis` → **RUNNING**
- HTTP **200** from inside the container network

---

## Rebuild image (optional)

Files in this folder are for rebuilding `jhay555/uidesired:uplary`:

```bash
docker build -f docker/Dockerfile -t jhay555/uidesired:uplary .
docker push jhay555/uidesired:uplary
```

The published UIDesired image uses its own entrypoint (`/usr/local/bin/uplary-entrypoint.sh` inside the image), not necessarily these files — coordinate with whoever maintains the Hub image.
