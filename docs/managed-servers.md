# Managed servers

When **managed servers** are enabled for your account, you can order a VPS directly from Uplary without connecting your own cloud provider token.

## How it works

1. Open **Servers → Add server → Managed**.
2. Choose a size/region from the catalog exposed by your plan.
3. Complete **Stripe checkout** for the selected size (if billing applies).
4. Uplary provisions the VPS on the backend provider, registers it in your account, and runs the standard bootstrap (Nginx, PHP-FPM, Redis, Supervisor, monitoring agent).
5. When status reaches **Ready**, add sites like any other server.

## Billing

Managed sizes are tied to your subscription. Plan changes, cancellations, and payment failures are described in [Plans & billing](stripe-billing.md). Contact support if a paid server remains in **Provisioning** for more than 30 minutes.

## Limitations

- Managed servers use the platform's provider relationship; you do not receive raw provider API tokens for those hosts.
- Snapshots and some provider-specific features may differ from BYOS Droplets on your own account.
- You still manage sites, deployments, SSL, and databases from the Uplary console.

## BYOS alternative

If you prefer to pay DigitalOcean (or another provider) directly, use [Providers & IPs](providers-and-ips.md) and [Provisioning](provisioning.md) instead of managed checkout.
