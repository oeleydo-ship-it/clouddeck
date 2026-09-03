# Getting help

Use these channels when documentation does not resolve your issue.

## In-app contact

Open **Contact support** from the documentation sidebar or footer form. Include:

- Account email
- Server or site domain affected
- Steps to reproduce
- Screenshots or redacted logs

## Operational logs

Before opening a ticket, check:

| Location | Shows |
|----------|-------|
| Site → Deployments | Build output, exit codes |
| Site → Operations | Configure, SSL, custom commands |
| Server → Operations | Bootstrap, firewall, restarts |
| Notifications → Incidents | Monitoring state |

## Common escalations

| Problem | Information to send |
|---------|---------------------|
| Deploy failure | Deployment UUID, last 50 lines of log |
| SSL failure | Domain, certificate type, Cloudflare SSL mode |
| Wrong site content | Domain, expected vs actual hostname, `curl -I` output |
| Database errors | Redacted `DB_CONNECTION` driver, attach status |

## Documentation index

Browse the full markdown library in the repository `docs/` folder or the in-app **Documentation** page at `/docs`.

## Status and maintenance

Platform-wide outages may be posted on your operator's status page if configured. Single-server issues are usually site- or host-specific.

## Super Admin / billing

Plan upgrades, invoice questions, and managed server billing: reference your Stripe customer email and server name from **Account → Billing**.
