# Custom domains (talaldocs)

Connect **`docs.yourcompany.com`** (or similar) so visitors see your brand instead of a talaldocs.com subdomain.

## Overview

| Step | Where |
|------|-------|
| Add domain in app | Workspace → Settings → Domains |
| Create CNAME | Your DNS registrar / Cloudflare |
| Verify | Same Domains screen in talaldocs |

## Before you start

- DNS access for `yourcompany.com`
- A **subdomain** hostname (recommended): `docs`, `help`, `kb`
- Apex/root (`yourcompany.com` without prefix) requires ALIAS/ANAME — subdomains are simpler

## Step 1 — Add domain in talaldocs

1. **Settings → Domains → Add custom domain**
2. Enter the full hostname, e.g. **`docs.yourcompany.com`**
3. Save and note verification instructions

## Step 2 — DNS record

Add at your DNS provider:

| Type | Name | Target |
|------|------|--------|
| **CNAME** | `docs` | `fallback.talaldocs.com` |

Examples:

- **Cloudflare**: CNAME `docs` → `fallback.talaldocs.com` (proxied or DNS-only)
- **GoDaddy / Namecheap**: Host `docs`, points to `fallback.talaldocs.com`

Do not use an A record to a random IP unless talaldocs explicitly instructs you.

## Step 3 — Verify

1. Wait 5–15 minutes (up to 24h globally).
2. Click **Verify** in talaldocs.
3. Status should become **Active**.

## Step 4 — Test

Open `https://docs.yourcompany.com` in a private window. You should see **your workspace**.

## SSL

Edge HTTPS for custom hostnames is provisioned via Cloudflare SSL for SaaS after verification. No manual certificate upload for standard setups.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Verification failed | Confirm CNAME target exactly `fallback.talaldocs.com` |
| Wrong workspace | Domain linked elsewhere — check workspace or contact support |
| 502 Bad Gateway | Platform/origin issue — contact support with domain name |
| Certificate warning | Allow time for SaaS cert issuance (up to 24h) |

## Need help?

Include workspace name, domain, DNS screenshot, and status from the Domains page.

## Operator configuration

Platform staff configure fallback origin and Cloudflare tokens in [admin-cloudflare-setup.md](admin-cloudflare-setup.md).
