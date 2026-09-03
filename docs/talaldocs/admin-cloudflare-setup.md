# Cloudflare setup (talaldocs admin)

Platform operators configure Cloudflare so **tenant subdomains** and **customer custom domains** work for talaldocs.com on Uplary.

## Prerequisites

- Cloudflare account owning **talaldocs.com**
- Uplary super-admin access
- talaldocs site deployed and **Ready** on a server with correct vhost/SSL

## Super Admin → Settings → DNS

| Field | Value |
|-------|-------|
| Enable Cloudflare integration | On |
| **Zone ID** | 32-character hex from Cloudflare zone overview |
| **Account ID** | Cloudflare account ID |
| **API token** | Token with Zone DNS + SSL for SaaS permissions |
| **Fallback origin** | `fallback.talaldocs.com` |
| Auto-provision custom hostnames | On (recommended) |

**Common mistake:** Zone ID is not your email address — copy it from the Cloudflare dashboard URL or zone sidebar.

## DNS records (talaldocs.com zone)

| Record | Purpose |
|--------|---------|
| `A` / `AAAA` for app server | Main app and wildcard origin |
| `CNAME fallback` → app origin | Target for customer CNAMEs |
| `CNAME *` or wildcard | Tenant subdomains `*.talaldocs.com` |

Ensure origin nginx serves the talaldocs vhost for:

- `talaldocs.com`
- `*.talaldocs.com`
- Custom hostnames routed via fallback

## SSL for SaaS / Custom Hostnames

Enable **SSL for SaaS** on the zone so customer domains like `docs.client.com` receive edge certificates after CNAME to `fallback.talaldocs.com`.

Origin requirements:

- Port **443** vhost for talaldocs (temporary self-signed is OK until LE/custom origin cert installed)
- For Cloudflare **Full (strict)**: install Cloudflare Origin certificate on the site via Uplary **Custom SSL**

## Deploy order (production checklist)

1. Deploy Uplary control plane with latest vhost/SSL fixes
2. Redeploy **talaldocs.com** site on origin server
3. Confirm HTTP/HTTPS serves talaldocs (not another default site)
4. Attach managed PostgreSQL if applicable; redeploy so `DB_*` syncs
5. Configure Cloudflare integration in super-admin
6. Test `https://acme.talaldocs.com` and a sample custom hostname

## Auto-provision API

When auto-provision is enabled, creating a custom domain in the talaldocs app queues Cloudflare custom hostname creation via API. Failures appear in application logs — verify token scopes include **Custom Hostnames Edit**.

## Troubleshooting

| Symptom | Cause |
|---------|-------|
| Customer domain shows wrong site | Origin default SSL vhost; missing talaldocs HTTPS block |
| 502 from Cloudflare | PHP/nginx down on origin; check site operations |
| Custom SSL install failed | Invalid PEM pair — use Cloudflare Origin cert + key |
| DB connection errors | Stale env — reattach database and redeploy |

## Related Uplary docs

- [DNS](../dns.md)
- [SSL certificates](../ssl-certificates.md)
- [Customer custom domain guide](custom-domains.md)
