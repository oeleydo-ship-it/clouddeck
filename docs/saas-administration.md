# SaaS administration

Uplary includes a super-administrator control center at `/admin` and customer billing and team workspaces at `/billing` and `/teams`.

## Plans and entitlements

Plans store monthly and yearly prices as integer cents, per-resource limits, and feature entitlements. A limit of `-1` means unlimited. The entitlement resolver selects an active or trialing subscription whose period has not expired, then falls back to the active `free` plan. When no plans exist, limits are intentionally unmetered so fresh developer and test installations remain usable.

The quota manager is enforced when customers create servers, sites, managed databases, API tokens, teams, or team members. Both web and API entry points use the same quota service. New registrations automatically receive the active free plan when one exists.

## Billing

Uplary supports both manual approval and Stripe-hosted subscription billing. A customer can request a public plan for offline review, or use Stripe Checkout when the plan has a mapped recurring Price ID. Manual approval atomically ends the prior entitlement and creates the new subscription; Stripe access is synchronized only from signed asynchronous webhooks.

Manual activation is isolated behind `App\Billing\Contracts\BillingGateway`. Stripe integration separately provides hosted Checkout, Customer Portal sessions, invoice history, automatic-tax requests, payment-failure notifications, and subscription lifecycle synchronization. The manual adapter does not collect money or issue invoices.

## Feature flags

Flags support global enablement, stable percentage rollouts, plan feature values, and user or plan overrides. Resolution order is user override, global state, plan override, plan entitlement, and stable rollout bucket. Cached flag state is invalidated by administrative changes.

Routes or actions that require staged rollout can use the `feature:<key>` middleware. Application services can inject `FeatureManager` for finer-grained checks.

## Teams

Customers can create teams, invite members as `member` or `admin`, accept email-bound invitations, and remove members. Invitation tokens are random, stored only as hashes, expire, and can be used once. Member limits include the owner.

This phase establishes membership and authorization-ready team context. Existing servers and sites remain owned by individual users; moving infrastructure ownership to teams requires an explicit resource-transfer and team-policy phase.

## Suspension, settings, and audit

Suspending an account revokes Sanctum tokens and database sessions immediately. Suspended accounts are rejected during password login, second-factor completion, and authenticated requests. Administrative changes and team actions write an audit record with actor, subject, IP address, user agent, and encrypted before/after payloads.

The control center can disable public registration and configure the support email and maintenance banner. Sensitive setting and audit payload values are encrypted at rest through Eloquent casts.

## Platform services (control-plane runtime)

Super admins can open **Admin → Platform services** (`/admin/platform-services`) to monitor this install’s own Redis, Horizon, queue workers, Reverb, and HTTPS/TLS — not customer-site Supervisor programs or site SSL tabs.

- Status is polled about every 7 seconds (`GET /admin/platform-services/status`).
- Redis shows connectivity (`PING`) and optional Docker control for the container named `uplary-redis` (override with `PLATFORM_REDIS_CONTAINER`). The panel never kills an unrelated system Redis process.
- Horizon start/stop uses `php artisan horizon` / `horizon:terminate` when `pcntl` and `posix` are available (Linux). On Windows the UI marks Horizon unavailable and recommends `queue:work`.
- Queue workers start `php artisan queue:work` against the same queues as `config/horizon.php` (default, operations, deployments, provisioning, notifications, monitoring, billing). PIDs for processes started here are stored under `storage/app/platform-services/`.
- Reverb start/stop uses `php artisan reverb:start` with port checks from `config/reverb.php`.
- **SSL / TLS** probes the host from `APP_URL` (HTTPS reachability, issuer, expiry, days remaining). Status values: `valid`, `expiring_soon` (&lt;30 days), `expired`, `not_https`, `unreachable`.
  - **Linux production-like hosts:** when `resources/scripts/renew-platform-ssl.sh` is present (override with `PLATFORM_SSL_RENEW_SCRIPT`), **Renew certificate** runs Certbot for the control-plane domain and reloads nginx (`POST /admin/platform-services/ssl/renew`). This is local-only — it does not SSH to customer servers or touch site certificates.
  - **Windows / local `artisan serve`:** Start/Stop N/A for TLS. If `APP_URL` is still `http://localhost`, the card explains that local serve is HTTP-only. Pointing `APP_URL` at a remote `https://` origin still shows live certificate status.

Horizon dashboard auth is unchanged: super admins always pass the gate; optional extra emails use `HORIZON_ALLOWED_EMAILS`.

## Staging sites

Staging is off until a superadmin enables **Staging sites** under Admin → Settings and optionally sets the platform staging apex (default `uplary.com`). Customers then create a linked staging site from a production site's Overview tab:

- **Platform subdomain** — `{slug}.staging.{platform_domain}` (for example `acme.staging.uplary.com`)
- **Client domain** — any FQDN such as `staging.client.com`

Staging is a separate site on the same server (own nginx vhost, release root, and environment). Laravel staging seeds `APP_ENV=staging`. **Promote to production** copies the staging repository, branch, script, and PHP version onto the linked production site and queues a production deployment. Create and promote routes return 404 while the platform toggle is off.

## Landing page, SEO, AI guide, and insert code

Dedicated admin sections (sidebar): **Pages**, **SEO**, **Analytics**, **Webmaster**, **Insert code**, **AI guide**. A superadmin can:

- Edit homepage hero, steps intro, and closing CTA copy (blank fields keep built-in defaults).
- Set default meta description, keywords, Open Graph image URL, robots, Google Analytics (GA4 measurement ID), and Google Search Console verification token. Tags are injected in the shared layout head.
- Paste custom HTML/JS (**Insert code**) for chat widgets and similar embeds into head/body. Defaults to marketing/public pages; console injection is optional. Raw markup is intentional for trusted operators only. Iframe-based widgets also need the widget host to allow framing — if that host is a site on this platform, enable **Allow embedding in iframes** under the site’s Remote → Nginx settings.
- Enable an **AI platform guide** with an encrypted OpenAI API key and optional system prompt. When enabled, signed-in users see a floating chat helper that answers how-to questions about the console (throttled at `/guide/chat`).

Stripe API keys and the webhook signing secret are configured under **Admin → Payments** (see `docs/stripe-billing.md`).

## Production checklist

- Create a separate super-admin account; the seeder intentionally does not grant administrator access.
- Configure Stripe live keys, signed webhooks, recurring plan Price IDs, tax registrations, and Customer Portal behavior before accepting online paid orders.
- Run queues with Redis and Horizon so invitation mail and infrastructure jobs are asynchronous.
- Set a secure `APP_KEY`, HTTPS cookies, trusted proxies, mail transport, and a persistent sessions table.
- Apply feature middleware to paid or staged surfaces according to the product policy.
- Back up subscription, audit, and team tables and define retention requirements before launch.
- Configure landing/SEO/analytics/insert-code and, if desired, the OpenAI guide key before launch.
