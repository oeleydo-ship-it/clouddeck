# Workers and cron

Laravel sites often need **queue workers**, **Horizon**, **Reverb**, and the **scheduler** running continuously on the server.

## Supervisor

Uplary writes Supervisor program definitions during deploy based on site settings:

| Program | Purpose |
|---------|---------|
| `queue:work` / Horizon | Process queued jobs |
| `reverb:start` | Laravel Reverb WebSocket server when enabled |
| Custom | Additional commands from site worker configuration |

Restart workers after deploy from the site dashboard or let deploy scripts reload Supervisor automatically.

## Scheduler

The system crontab invokes Laravel's scheduler every minute:

```cron
* * * * * cd /var/www/example.com/current && php artisan schedule:run >> /dev/null 2>&1
```

Ensure `schedule:run` is never duplicated in both crontab and a long-running process.

## Horizon

When Horizon is enabled, use the Horizon dashboard route configured in your Laravel app. Uplary ensures the Horizon Supervisor program points at the correct release path after each atomic switch.

## Reverb

WebSocket services bind to the port configured in the site environment (for example `8082` locally on the control plane). Open firewall ports on the server if browsers connect directly; behind Nginx, configure proxy pass to the Reverb port.

## React and WordPress

- **React** sites do not use PHP workers unless you colocate a Laravel API on the same server separately.
- **WordPress** may use wp-cron over HTTP; high-traffic sites should disable wp-cron HTTP and use system cron instead.

## Monitoring

Supervisor health appears in [monitoring](monitoring.md) samples. Auto-heal can restart Supervisor when consecutive down samples are reported (opt-in per server).

## Operations

Allowlisted restart commands (`supervisor:restart`, `php:restart`) are available under **Server → Operations**. See [Server operations](server-operations.md).
