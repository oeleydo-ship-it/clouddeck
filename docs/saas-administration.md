# SaaS administration

Uplary includes a super-administrator control center at `/admin` and customer billing and team workspaces at `/billing` and `/teams`.

## Plans and entitlements

Plans store monthly and yearly prices as integer cents, per-resource limits, and feature entitlements. A limit of `-1` means unlimited. The entitlement resolver selects an active or trialing subscription whose period has not expired, then falls back to the active `free` plan. When no plans exist, limits are intentionally unmetered so fresh developer and test installations remain usable.

The quota manager is enforced when customers create BYOS servers, managed servers, sites, managed databases, API tokens, teams, or team members. Both web and API entry points use the same quota service. New registrations automatically receive the active free plan when one exists.

Boolean plan features (catalog in `config/plan-features.php`) gate console modules and site capabilities sold with a plan. **BYOS servers and managed servers are priced separately**, and **site quotas are also split by host type**:

- **BYOS** — customer connects their own cloud (`providers`) and provisions through it, or adds a server by SSH; counts against the `servers` limit. Sites on those hosts count against `sites` (BYOS sites). Both `/cloud-accounts` and `/servers/create` (the provision-with-your-cloud wizard) require the `providers` feature; `/servers/custom` (add existing server by SSH) does not, since it needs no provider connection
- **Managed servers** — platform creates the VPS on the control-plane cloud API token; requires Admin → Managed servers (enabled + token), plan feature `managed_servers`, and the `managed_servers` limit. Sites on those hosts count against `managed_sites`. Gated the same way as BYOS: `feature:managed_servers` plus `EnsureManagedServersEnabled` on `/servers/managed`, vs `feature:providers` on `/servers/create`

A Free plan can therefore allow e.g. **1 BYOS site** and **5 managed sites** independently. When **Admin → Managed servers** is disabled, public pricing (landing page) and the customer billing plan cards hide managed server/site quotas entirely so the product is not advertised while the platform feature is off.

| Key | Gates |
| --- | --- |
| `providers` | Cloud accounts and the BYOS provision wizard (`/cloud-accounts`, `/servers/create`) |
| `managed_servers` | Platform-provided VPS provision UI (`/servers/managed`) |
| `firewall`, `security`, `notifications`, `dns`, `ssh` | Matching sidebar entries and route groups |
| `monitoring` | Server and site monitoring actions |
| `remote_management` | Remote configuration, files, and terminal |
| `teams` | `/teams` and the account menu link |
| `staging` | Staging create/promote (also requires the platform staging toggle) |
| `backups` | Backup policies and snapshots |
| `horizon`, `reverb` | Installing those Laravel packages on a site |
| `redis` | Creating and managing queue / Horizon / Reverb workers |

DNS still requires the platform **DNS** setting as well as the plan `dns` entitlement. Control-plane **Admin → Platform services** (this install’s Redis, Horizon, Reverb, queue workers) is operator-only and is not sold on customer plans.

## Billing

Uplary supports both manual approval and Stripe-hosted subscription billing. A customer can request a public plan for offline review, or use Stripe Checkout when the plan has a mapped recurring Price ID. Manual approval atomically ends the prior entitlement and creates the new subscription; Stripe access is synchronized only from signed asynchronous webhooks.

Manual activation is isolated behind `App\Billing\Contracts\BillingGateway`. Stripe integration separately provides hosted Checkout, Customer Portal sessions, invoice history, automatic-tax requests, payment-failure notifications, and subscription lifecycle synchronization. The manual adapter does not collect money or issue invoices.

## Feature flags

Flags support global enablement, stable percentage rollouts, plan feature values, and user or plan overrides. Resolution order is user override, global state, plan override, plan entitlement, and stable rollout bucket. Cached flag state is invalidated by administrative changes. Super-admins always pass feature checks. When no plan is entitled, features stay open (same unmetered posture as quotas).

Routes or actions that require staged rollout can use the `feature:<key>` middleware. Application services can inject `FeatureManager` for finer-grained checks. Plan booleans alone are enough when no matching `FeatureFlag` row exists. An empty `features` map (legacy unmetered plans) still grants every catalog entitlement; once any keys are stored, missing keys are denied.

## Teams

Customers create teams at `/teams`, invite members by email as `viewer`, `operator`, or `admin`, accept email-bound invitations, and manage members. The creator is `owner`. Invitation tokens are random, stored only as hashes, expire (typically 7 days), and can be used once. Member limits include the owner.

Pending invitations support **Edit** (change role before acceptance), **Resend** (rate-limited), and **Delete** (cancel). Owners and admins can change accepted members’ roles or remove them; the owner cannot be demoted or removed by others.

| Role | Can | Cannot |
| --- | --- | --- |
| Owner | Manage members/invitations; view, operate, transfer, and delete team servers; switch active workspace | Be removed or demoted by other members |
| Admin | Invite, edit, resend, cancel invitations; change roles and remove members; view, operate, transfer, and delete team servers | Remove or demote the owner |
| Operator | View and operate team servers (deploy, configure) | Manage members/invitations; transfer or delete servers |
| Viewer | View team servers and related console pages | Deploy or change servers; manage members; transfer or delete |

Authorization helpers: `TeamAccess::canOperate` covers owner/admin/operator; `TeamAccess::canManage` covers owner/admin.

## Suspension, settings, and audit

Suspending an account revokes Sanctum tokens and database sessions immediately. Suspended accounts are rejected during password login, second-factor completion, and authenticated requests. Administrative changes and team actions write an audit record with actor, subject, IP address, user agent, and encrypted before/after payloads.

The control center can disable public registration and configure the support email and maintenance banner. Sensitive setting and audit payload values are encrypted at rest through Eloquent casts.

## Platform services (control-plane runtime)

Super admins can open **Admin â†’ Platform services** (`/admin/platform-services`) to monitor this installâ€™s own Redis, Horizon, queue workers, Reverb, and HTTPS/TLS â€” not customer-site Supervisor programs or site SSL tabs.

- Status is polled about every 7 seconds (`GET /admin/platform-services/status`).
- Redis shows connectivity (`PING`) and optional Docker control for the container named `uplary-redis` (override with `PLATFORM_REDIS_CONTAINER`). The panel never kills an unrelated system Redis process.
- Horizon start/stop uses `php artisan horizon` / `horizon:terminate` when `pcntl` and `posix` are available (Linux). On Windows the UI marks Horizon unavailable and recommends `queue:work`.
- Queue workers start `php artisan queue:work` against the same queues as `config/horizon.php` (default, operations, deployments, provisioning, notifications, monitoring, billing). PIDs for processes started here are stored under `storage/app/platform-services/`.
- Reverb start/stop uses `php artisan reverb:start` with port checks from `config/reverb.php`.
- **SSL / TLS** probes the host from `APP_URL` (HTTPS reachability, issuer, expiry, days remaining). Status values: `valid`, `expiring_soon` (&lt;30 days), `expired`, `not_https`, `unreachable`.
  - **Linux production-like hosts:** when `resources/scripts/renew-platform-ssl.sh` is present (override with `PLATFORM_SSL_RENEW_SCRIPT`), **Renew certificate** runs Certbot for the control-plane domain and reloads nginx (`POST /admin/platform-services/ssl/renew`). This is local-only â€” it does not SSH to customer servers or touch site certificates.
  - **Windows / local `artisan serve`:** Start/Stop N/A for TLS. If `APP_URL` is still `http://localhost`, the card explains that local serve is HTTP-only. Pointing `APP_URL` at a remote `https://` origin still shows live certificate status.

Horizon dashboard auth is unchanged: super admins always pass the gate; optional extra emails use `HORIZON_ALLOWED_EMAILS`.

## Managed servers

Managed servers are platform-billed VPS (separate from BYOS), configured on their own **Admin → Managed servers** tab. Off until a superadmin enables **Managed servers**, chooses DigitalOcean, and saves a platform API token (encrypted). Customers with the plan feature `managed_servers` and remaining `managed_servers` quota open `/servers/managed`, pick region/size/image, and provision without connecting their own cloud account. Created servers store `provisioning_source=managed` and use `CloudProviderManager::forPlatform()` / `forServer()` for create, wait, destroy, and snapshots. BYOS (`servers` limit) and managed quotas are counted separately.

### Markup pricing

The same **Admin → Managed servers** tab lists every size the platform cloud account offers (1 GB, 4 GB, 8 GB, …) alongside its raw infra cost. Two ways to price them for customers:

- **Default markup %** — applied over infra cost for any size without an explicit override (`SystemSettings::managedMarkupPercent()`).
- **Per-size price override** — an exact customer price for one configuration, stored in `managed_size_prices` (JSON keyed by provider size slug), read via `SystemSettings::managedSizePrices()`.

`SystemSettings::managedServerPrice($size)` resolves the final customer price (override, else infra × (1 + markup%)). The managed-server wizard shows this price to the customer and stores both `infra_price_monthly` and `customer_price_monthly` on the server's `metadata` at deploy time.

## Staging sites

Staging is off until a superadmin enables **Staging sites** under Admin â†’ Settings and optionally sets the platform staging apex (default `uplary.com`). Customers then create a linked staging site from a production site's Overview tab:

- **Platform subdomain** â€” `{slug}.staging.{platform_domain}` (for example `acme.staging.uplary.com`)
- **Client domain** â€” any FQDN such as `staging.client.com`

Staging is a separate site on the same server (own nginx vhost, release root, and environment). Laravel staging seeds `APP_ENV=staging`. **Promote to production** copies the staging repository, branch, script, and PHP version onto the linked production site and queues a production deployment. Create and promote routes return 404 while the platform toggle is off.

## Landing page, SEO, AI, and insert code

Dedicated admin sections (sidebar): **Pages**, **SEO**, **Analytics**, **Webmaster**, **Insert code**, **AI**. A superadmin can:

- Edit homepage hero, steps intro, and closing CTA copy (blank fields keep built-in defaults).
- Configure SEO under **Admin → SEO**: default title and `{page} | {site}` title template, default meta description / keywords / Open Graph image / meta robots, homepage and marketing-page overrides, and a full **robots.txt** body. Public routes serve `/sitemap.xml` (marketing pages + published posts) and `/robots.txt`. Per-post `meta_title` / `meta_description` are editable on Blog create/edit.
- Set Google Analytics (GA4 measurement ID) and Google Search Console verification under **Analytics** / **Webmaster**. Tags are injected in the shared layout head.
- Paste custom HTML/JS (**Insert code**) for chat widgets and similar embeds into head/body. Defaults to marketing/public pages; console injection is optional. Raw markup is intentional for trusted operators only. Iframe-based widgets also need the widget host to allow framing â€” if that host is a site on this platform, enable **Allow embedding in iframes** under the siteâ€™s Remote â†’ Nginx settings.
- Under **Admin → AI**, choose a provider (**OpenAI** or **Moonshot / Kimi**), save an encrypted API key and model, optionally override the OpenAI-compatible base URL, then enable features independently:
  - **OpenAI** — default model `gpt-4o-mini`, base URL `https://api.openai.com/v1`.
  - **Moonshot (Kimi)** — default model `kimi-k3` (also `kimi-k2.6`, `kimi-k2.5`, etc.); international base URL `https://api.moonshot.ai/v1`, China region `https://api.moonshot.cn/v1` via the optional Base URL field. Keys from [platform.kimi.ai](https://platform.kimi.ai/console/api-keys).
  - **AI platform guide** — signed-in users see a floating chat helper for console how-tos (throttled at `/guide/chat`). Optional custom system prompt.
  - **AI blog drafts** — on **Admin → Blog**, use **Suggest topics** / **Generate draft** to fill a new-post form with platform-aware plain-text content (servers, sites, deployments, staging, monitoring, etc.). Drafts are never auto-published; endpoints are throttled under `/admin/posts/ai/*` and require the blog toggle plus API key. Under **Admin → AI** you can train voice with **phrases to avoid** (defaults ban clichés like “digital world” / “fast-paced digital landscape”), optional **words to weave in**, and free-form **style notes**. Matching avoid-phrases are scrubbed from generated drafts.

Stripe API keys and the webhook signing secret are configured under **Admin → Payments** (see `docs/stripe-billing.md`).

## Google Sign-In (OAuth)

Configure under **Admin → Google Auth** (`/admin/google-auth`), or via `.env` as a fallback:

1. In [Google Cloud Console](https://console.cloud.google.com/) create an OAuth 2.0 Client ID (application type **Web application**).
2. Add an authorized redirect URI of `{APP_URL}/auth/google/callback` (shown read-only on the admin page).
3. Paste Client ID and Client Secret, enable Google sign-in, and save. The secret is encrypted and never re-displayed (blank keeps the stored value).
4. Optional `.env` keys: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_AUTH_ENABLED` (default `true` when no admin toggle has been saved yet). Saved admin settings override env.
5. Only Google accounts with a **verified** email are accepted. New users receive the default `customer` role (never `super_admin`). Matching emails auto-link `google_id` to an existing password account without changing role. Two-factor challenge still applies when enabled on the account.

Continue with Google appears on login and register only when Google Auth is enabled and both credentials are available.

## Firewall (customer console)

Per-server UFW rules are managed from the customer console **Firewall** page (sidebar, below Sites), not from `/admin`. Operators document add/delete, **Apply to server**, **Refresh remote status**, rule fields, and sync statuses in [Server operations — Firewall](server-operations.md#firewall).

## Production checklist


- Create a separate super-admin account; the seeder intentionally does not grant administrator access.
- Configure Stripe live keys, signed webhooks, recurring plan Price IDs, tax registrations, and Customer Portal behavior before accepting online paid orders.
- Run queues with Redis and Horizon so invitation mail and infrastructure jobs are asynchronous.
- Set a secure `APP_KEY`, HTTPS cookies, trusted proxies, mail transport, and a persistent sessions table.
- Apply feature middleware to paid or staged surfaces according to the product policy.
- Back up subscription, audit, and team tables and define retention requirements before launch.
- Configure landing/SEO/analytics/insert-code and, if desired, an AI provider key (guide and/or blog drafts) before launch.
