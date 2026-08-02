# Stripe subscription billing

CloudDeck supports Stripe-hosted subscription Checkout and Customer Portal sessions. Card and payment-method details never pass through or persist in CloudDeck.

## Configuration

Set the following production secrets:

```dotenv
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_AUTOMATIC_TAX=true
```

In the super-admin **Stripe** tab, map each paid plan to its monthly and yearly recurring Stripe Price IDs. Plans without a mapped price remain available through the manual billing-request workflow but are not shown in hosted checkout.

Register `POST /api/billing/stripe/webhook` as a Stripe webhook endpoint. Subscribe to `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, `invoice.payment_failed`, and other invoice lifecycle events that your operating policy needs.

## Security and processing

The endpoint verifies the `Stripe-Signature` against the unmodified request body and rejects timestamps outside a five-minute tolerance. Provider event IDs are unique, payloads are encrypted at rest, and newly accepted events are dispatched to the dedicated `billing` Horizon queue. Replayed events are acknowledged without being processed twice.

Checkout metadata binds the CloudDeck user and plan to the provider session and subscription. Entitlements become active only from Stripe subscription lifecycle state, not from the success redirect. Webhook ordering is tolerated: a late Checkout event cannot downgrade an already-active subscription.

## Subscription and invoice lifecycle

Active and trialing Stripe subscriptions replace prior active entitlements. Canceled, unpaid, incomplete, paused, and past-due states do not pass the entitlement check. Period dates and cancel-at-period-end state are synchronized from provider events.

Invoice events persist amounts as integer minor units, calculated tax, status, payment date, hosted invoice URL, and PDF URL. Payment failure queues mail and database notifications. Customers with a Stripe customer ID can open a short-lived Stripe Customer Portal session to update payment details, review invoices, change plans according to the portal configuration, or cancel.

Stripe recommends using asynchronous subscription events as the source of truth and verifying every webhook from the raw body. See the official [subscription webhook guide](https://docs.stripe.com/billing/subscriptions/webhooks), [webhook verification guide](https://docs.stripe.com/webhooks), [Checkout Sessions API](https://docs.stripe.com/api/checkout/sessions/create), and [Customer Portal API](https://docs.stripe.com/api/customer_portal/sessions/create).

## Operations checklist

- Run Horizon with the `billing` and `notifications` supervisors.
- Keep live and test webhook secrets separate and rotate them through environment configuration.
- Configure Stripe Customer Portal products and cancellation behavior before enabling it for customers.
- Configure Stripe Tax registrations and product tax codes; enabling automatic tax alone does not establish registrations.
- Reconcile failed webhook rows and Stripe Workbench delivery failures.
- Test checkout, renewals, payment failures, cancellation, and tax behavior in Stripe test mode before adding live keys.
