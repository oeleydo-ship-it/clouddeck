# Teams and API

Collaborate with teammates and automate Uplary from your own tools.

## Teams

Account owners invite members with roles:

| Role | Typical access |
|------|----------------|
| **Owner** | Billing, delete account, all resources |
| **Admin** | Servers, sites, deployments, settings |
| **Member** | Create and manage assigned resources |
| **Read-only** | View without mutating |

Invitations are email-based. Pending invites can be revoked from **Account → Team**.

## API tokens

Create **Sanctum personal access tokens** under **Account → API tokens**. Tokens authenticate REST endpoints scoped by abilities (for example `servers:read`, `sites:write`).

Store tokens securely. Uplary displays the plain token once at creation.

### Example

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  https://uplary.example.com/api/servers
```

Refer to the API route list in the application or OpenAPI export if published for your deployment.

## Webhooks (outbound)

Site Git webhooks are **inbound** to Uplary. Outbound integrations (Slack, Discord) use notification channels — see [Notifications](notifications.md).

## SaaS administration

Platform operators use the **Super Admin** panel for tenants, plans, DNS integration, and feature flags. See [SaaS administration](saas-administration.md).

## Related

- [Account security](account-security.md)
- [Deployment engine — webhooks](deployment-engine.md)
