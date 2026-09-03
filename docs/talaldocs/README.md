# talaldocs documentation

Guides for **talaldocs.com** — a multi-tenant documentation workspace product deployed on Uplary.

## Audiences

| Guide | Audience |
|-------|----------|
| [Workspaces and subdomains](workspaces-and-subdomains.md) | Workspace owners |
| [Custom domains](custom-domains.md) | Customers connecting `docs.client.com` |
| [Cloudflare setup (admin)](admin-cloudflare-setup.md) | Platform operators / super-admin |

## Quick links

- **Subdomain**: `https://[slug].talaldocs.com` — configure in workspace Settings → Domains
- **Custom domain**: CNAME `docs` → `fallback.talaldocs.com`, then verify in the app
- **Operator fallback origin**: `fallback.talaldocs.com` must resolve to the Uplary-managed site server

Legacy single-file help: [../talaldocs-custom-domains-help.md](../talaldocs-custom-domains-help.md)
