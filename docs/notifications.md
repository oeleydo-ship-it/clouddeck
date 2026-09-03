# Notifications

Uplary delivers operational events through in-app notifications, email, and optional third-party channels.

## In-app bell

The header bell shows unread operational events: deployments, backups, monitoring incidents, auto-heal actions, and billing notices. Mark items read individually or clear the list from the notifications index.

## Email recipients

Configure account-wide **email recipients** under **Notifications → Email recipients**. These addresses receive monitoring incidents, backup failures, and other operational mail when enabled for each event type.

## Per-server channels

On each server's **Monitoring** tab, optionally enable:

| Channel | Configuration |
|---------|----------------|
| **Slack** | Incoming webhook URL (HTTPS, official Slack host only) |
| **Discord** | Webhook URL (official Discord host only) |
| **Telegram** | Bot token and chat ID |

Credentials are encrypted at rest. Delivery runs on the `notifications` queue.

## Incidents

**Notifications → Incidents** lists open and resolved monitoring incidents across servers and sites. Filter by severity, server, or status. Incidents auto-resolve when metrics return to normal or probes succeed.

## Event types

Common notification types include:

- Deployment succeeded / failed
- SSL issued / failed
- Server offline / recovered
- Site down / DNS mismatch
- Backup completed / failed
- Auto-heal service restart
- Queue health degradation (Laravel sites)

## Cooldowns

Alert rules and site probes use cooldown windows to avoid notification storms. Adjust thresholds and consecutive sample requirements on the Monitoring tab.

## Related

- [Monitoring and alerts](monitoring.md)
- [Getting help](getting-help.md)
