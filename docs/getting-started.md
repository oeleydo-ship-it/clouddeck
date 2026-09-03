# Getting started

Uplary is a SaaS control plane for deploying and operating Laravel, WordPress, and React applications on your own cloud infrastructure or managed VPS sizes.

## What you need

- An Uplary account
- Either a cloud provider token (DigitalOcean today) **or** an existing Ubuntu VPS reachable over SSH
- A domain name pointed at your server (or use the server IP while testing)
- For Laravel sites: a Git repository the server can clone

## Quick path

1. **Sign up** and confirm your email if required.
2. **Add an SSH key** under Account → SSH keys (generate or paste a public key).
3. **Connect a provider** or **attach a server by IP** under Servers.
4. Wait until the server status is **Ready** (provisioning installs Nginx, PHP-FPM, Redis, Supervisor, and the monitoring agent).
5. **Create a site** — choose Laravel, WordPress, or React, enter the domain, repository (if applicable), and PHP version.
6. **Configure DNS** so the domain resolves to the server public IP (or use Cloudflare integration from the DNS tab).
7. **Deploy** from the site dashboard. SSL can be issued automatically after the site is configured.

## Console layout

| Area | Purpose |
|------|---------|
| **Servers** | Provision, attach, monitor, and maintain hosts |
| **Sites** | Applications, deployments, environment, SSL, databases |
| **Notifications** | Incidents, alert channels, email recipients |
| **Account** | Profile, billing, SSH keys, API tokens, team |

## Platform types

| Platform | Use case |
|----------|----------|
| **Laravel** | PHP apps with Composer, migrations, queues, zero-downtime releases |
| **WordPress** | Downloaded from wordpress.org; attach a database before first deploy |
| **React** | Vite SPA builds served by Nginx with `index.html` fallback — no PHP runtime required |

## Managed vs bring-your-own-server (BYOS)

- **BYOS**: Connect DigitalOcean (or attach any Ubuntu VPS). You pay the provider directly.
- **Managed servers** (when enabled on your plan): Order a VPS size through Uplary checkout; provisioning runs automatically after payment.

## Next steps

- [Adding a site](sites.md)
- [Deployments](deployment-engine.md)
- [SSL certificates](ssl-certificates.md)
- [Getting help](getting-help.md)
