# DNS

Uplary integrates with **Cloudflare** to list zones, manage records, and support SaaS custom hostnames for multi-tenant applications.

## Connect Cloudflare

Super Admin or account DNS settings (depending on deployment):

1. Create a Cloudflare **API token** with Zone DNS Edit (and SSL for SaaS if using custom hostnames).
2. Enter **Account ID**, **Zone ID**, and token in **Settings → DNS** (super-admin) or the site/server DNS tab.
3. Enable integration and set **fallback origin** hostname for custom hostname products (e.g. `fallback.talaldocs.com`).

Zone ID is a 32-character hex string from the Cloudflare zone overview — not your account email.

## Records from Uplary

For standard sites, create **A** or **CNAME** records pointing to the server public IP or proxied target. Uplary can display recommended values on the site DNS tab.

## Cloudflare proxy (orange cloud)

Proxied records terminate TLS at Cloudflare. Origin must expose matching HTTPS vhosts — see [SSL certificates](ssl-certificates.md).

SSL modes:

| Mode | Origin requirement |
|------|-------------------|
| Flexible | HTTP on origin port 80 |
| Full | HTTPS with any certificate |
| Full (strict) | Valid origin certificate (LE or Cloudflare Origin PEM) |

## SaaS custom hostnames

For products like talaldocs where customers bring `docs.client.com`:

1. Customer CNAME → `fallback.yourproduct.com`
2. Cloudflare **SSL for SaaS** / Custom Hostnames validates and issues edge certificates
3. Platform auto-provision API creates hostname entries when enabled

Operator guide: [talaldocs/admin-cloudflare-setup.md](talaldocs/admin-cloudflare-setup.md).

## Verification

Use the site monitoring **DNS mismatch** probe to detect when public DNS no longer matches the server IP.

## Troubleshooting

| Issue | Fix |
|-------|-----|
| DNS mismatch alert | Update A/AAAA at registrar or Cloudflare |
| Wrong site on HTTPS | Missing origin vhost; reconfigure site |
| Token errors | Regenerate token with correct zone scope |
