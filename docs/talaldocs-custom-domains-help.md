# Connect your workspace domain

Use a **talaldocs.com subdomain** (for example `acme.talaldocs.com`) or your **own domain** (for example `docs.yourcompany.com`) so visitors reach your workspace at a branded address.

---

## Choose the right option

| Option | Example | Best for |
|--------|---------|----------|
| **Subdomain** | `acme.talaldocs.com` | Quick setup, no DNS changes at your registrar |
| **Custom domain** | `docs.yourcompany.com` | Your brand on your own hostname |

You can use both: a subdomain for testing and a custom domain for production.

---

## Option 1 — Use a talaldocs.com subdomain

### Steps

1. Open your **workspace** in talaldocs.
2. Go to **Settings → Domains** (or **Domain** during setup).
3. Enter the subdomain you want, for example `acme` → **`acme.talaldocs.com`**.
4. Save.

### What happens

- Your site will be available at `https://acme.talaldocs.com`.
- HTTPS is handled automatically — no certificate upload needed.
- Changes may take a few minutes to become visible worldwide.

### Tips

- Use only letters, numbers, and hyphens in the subdomain name.
- If the name is already taken, try another (for example `acme-docs`).

---

## Option 2 — Connect your own custom domain

Use this when you want visitors to see **`docs.yourcompany.com`** (or similar) instead of a talaldocs.com address.

### Before you start

- You need access to **DNS settings** at your domain registrar (GoDaddy, Namecheap, Cloudflare, etc.).
- Decide the exact hostname, for example:
  - `docs.yourcompany.com` (most common)
  - `help.yourcompany.com`
- Custom domains usually cannot use the bare apex (`yourcompany.com`) with a simple CNAME — use a subdomain like `docs`.

### Step 1 — Add the domain in talaldocs

1. Open your **workspace**.
2. Go to **Settings → Domains → Add custom domain**.
3. Enter the full domain, for example **`docs.yourcompany.com`**.
4. Save.

The app will show **verification instructions** (DNS records). Keep this page open.

### Step 2 — Add DNS at your registrar

Log in where **`yourcompany.com`** is managed and add the record talaldocs shows you.

**Most common setup (recommended):**

| Type | Name / Host | Target / Value | TTL |
|------|-------------|----------------|-----|
| **CNAME** | `docs` | `fallback.talaldocs.com` | Auto / 300 |

Examples by registrar:

- **Cloudflare:** Type CNAME, Name `docs`, Target `fallback.talaldocs.com`, Proxy status **Proxied** (orange cloud) or **DNS only** (grey) — either works once verification completes.
- **GoDaddy:** Host `docs`, Points to `fallback.talaldocs.com`.
- **Namecheap:** Host `docs`, Value `fallback.talaldocs.com`.

> **Important:** Point to **`fallback.talaldocs.com`**, not an IP address, unless talaldocs explicitly tells you otherwise.

### Step 3 — Verify in talaldocs

1. Wait **5–15 minutes** for DNS to propagate (sometimes up to 24 hours).
2. Return to **Settings → Domains** in talaldocs.
3. Click **Verify** or **Check status**.

When verification succeeds, status changes to **Active** or **Connected**.

### Step 4 — Test

Open **`https://docs.yourcompany.com`** in a private/incognito window.

You should see **your workspace**, not another site or an error page.

---

## SSL / HTTPS

- **Subdomains** (`*.talaldocs.com`): HTTPS is automatic.
- **Custom domains**: HTTPS is provisioned after DNS verification. No manual certificate upload is required for standard setups.

If the browser shows a certificate warning, wait a few more minutes and try again. Contact support if it persists after 24 hours.

---

## Troubleshooting

### “Domain pending” or “Verification failed”

| Check | Action |
|-------|--------|
| CNAME target | Must be exactly `fallback.talaldocs.com` (no `https://`, no trailing dot unless your registrar requires it) |
| Wrong record type | Use **CNAME** for subdomains like `docs`; do not use A record unless instructed |
| Typo in hostname | Domain in talaldocs must match DNS exactly (`docs.yourcompany.com`) |
| Propagation | Wait 15–60 minutes; use [https://dnschecker.org](https://dnschecker.org) to confirm CNAME globally |

### “This domain is already in use”

Another workspace may own that domain. Use a different hostname or contact your organization admin.

### Site loads but shows the wrong workspace

The domain may be linked to a different workspace. Check **Settings → Domains** in the correct workspace or contact support.

### 502 Bad Gateway or blank page

This is usually a platform issue, not your DNS. Contact **talaldocs support** with your domain name and workspace URL.

### Apex domain (`yourcompany.com` without `docs.`)

Root/apex domains often need **ALIAS**, **ANAME**, or **CNAME flattening** (Cloudflare supports this). Subdomains like `docs.yourcompany.com` are much simpler — use those when possible.

---

## Quick reference

**Subdomain**

```
https://[your-slug].talaldocs.com
→ Create in workspace Settings → Domains
→ No external DNS required
```

**Custom domain**

```
https://docs.yourcompany.com
→ Add domain in talaldocs
→ CNAME docs → fallback.talaldocs.com
→ Verify in talaldocs
```

---

## Need help?

Contact support and include:

- Your **workspace name**
- The **domain** you are connecting
- A screenshot of your **DNS record** at your registrar
- The **status** shown in talaldocs (pending / failed / active)

We can usually spot CNAME typos and propagation delays quickly.

---

*This article is for workspace owners and admins. Platform operators configure Cloudflare and the fallback origin separately in Super Admin settings.*
