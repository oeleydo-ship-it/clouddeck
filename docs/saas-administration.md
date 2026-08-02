# SaaS administration

CloudDeck includes a super-administrator control center at `/admin` and customer billing and team workspaces at `/billing` and `/teams`.

## Plans and entitlements

Plans store monthly and yearly prices as integer cents, per-resource limits, and feature entitlements. A limit of `-1` means unlimited. The entitlement resolver selects an active or trialing subscription whose period has not expired, then falls back to the active `free` plan. When no plans exist, limits are intentionally unmetered so fresh developer and test installations remain usable.

The quota manager is enforced when customers create servers, sites, managed databases, API tokens, teams, or team members. Both web and API entry points use the same quota service. New registrations automatically receive the active free plan when one exists.

## Billing

CloudDeck supports both manual approval and Stripe-hosted subscription billing. A customer can request a public plan for offline review, or use Stripe Checkout when the plan has a mapped recurring Price ID. Manual approval atomically ends the prior entitlement and creates the new subscription; Stripe access is synchronized only from signed asynchronous webhooks.

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

## Production checklist

- Create a separate super-admin account; the seeder intentionally does not grant administrator access.
- Configure Stripe live keys, signed webhooks, recurring plan Price IDs, tax registrations, and Customer Portal behavior before accepting online paid orders.
- Run queues with Redis and Horizon so invitation mail and infrastructure jobs are asynchronous.
- Set a secure `APP_KEY`, HTTPS cookies, trusted proxies, mail transport, and a persistent sessions table.
- Apply feature middleware to paid or staged surfaces according to the product policy.
- Back up subscription, audit, and team tables and define retention requirements before launch.
