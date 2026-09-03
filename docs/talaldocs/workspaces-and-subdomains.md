# Workspaces and subdomains

Each **workspace** in talaldocs can be reached at a branded **talaldocs.com subdomain**.

## Choose a subdomain

1. Sign in and open your workspace.
2. Go to **Settings → Domains**.
3. Enter a slug (letters, numbers, hyphens), for example `acme` → **`acme.talaldocs.com`**.
4. Save.

## What you get

- Public URL: `https://acme.talaldocs.com`
- Automatic HTTPS on `*.talaldocs.com` (platform-managed)
- No DNS changes at your registrar

## Naming rules

- Slugs must be unique across the platform.
- If `acme` is taken, try `acme-docs` or include your company name.
- Subdomains propagate within a few minutes globally.

## Workspace vs subdomain

One workspace maps to one primary subdomain unless you also add [custom domains](custom-domains.md). Content, members, and billing are scoped to the workspace.

## Testing before custom domain

Use a subdomain to validate content and permissions before pointing `docs.yourcompany.com` at production.

## Troubleshooting

| Issue | Action |
|-------|--------|
| Subdomain unavailable | Pick another slug |
| 404 or wrong workspace | Confirm you are signed into the correct workspace |
| SSL warning | Wait 15 minutes; contact support if persistent |

## Related

- [Custom domains](custom-domains.md)
- [Cloudflare setup for operators](admin-cloudflare-setup.md)
