# Staging sites

When **staging sites** are enabled for your account, you can run a pre-production copy of a site on the same server before promoting changes to production.

## Create staging

1. Open the **production site → Staging** tab.
2. Enter a staging domain (for example `staging.app.example.com`).
3. Uplary links the staging site record, configures a separate vhost, and shares or copies deployment settings as configured.

## Workflow

| Step | Action |
|------|--------|
| Develop | Push to the staging branch or deploy manually to staging |
| Test | Verify on the staging URL with optional basic auth |
| Promote | Copy the active staging release to production with one action |

Promotion performs an atomic switch on production using the tested release artifact where supported.

## DNS

Create a DNS record for the staging subdomain pointing to the same server IP as production. Issue SSL separately for the staging hostname.

## Environment

Staging maintains its own environment variables. Use distinct `APP_URL`, database names, and API keys so staging never sends live email or charges real cards.

## Limitations

- Staging shares the server with production — resource contention is possible on small VPS sizes.
- WordPress staging may require separate database tables or a cloned database dump.
- React staging builds use the same pipeline; ensure `VITE_*` URLs target staging APIs.

## Related

- [Adding a site](sites.md)
- [Deployments](deployment-engine.md)
