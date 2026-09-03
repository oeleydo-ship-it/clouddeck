# Server maintenance

Keep managed hosts patched and healthy from **Server → Maintenance** and **Operations**.

## Package updates

Queue **apt update/upgrade** style maintenance from the Maintenance tab. Output is captured in server operation logs. Schedule during low-traffic windows; PHP-FPM may restart when security updates apply.

## Release upgrades

Major Ubuntu release upgrades are not fully automated. Plan migrations from 22.04 to 24.04 with snapshots, test on a clone, and re-run provisioning scripts on the new image when supported.

## Software hardening

Bootstrap applies baseline hardening (non-root deploy user where configured, UFW, fail2ban optional). Review [Firewall](firewall.md) and [Security detection](security-detection.md) periodically.

## Allowlisted operations

The **Operations** tab exposes safe, audited commands:

- Nginx / PHP-FPM / MySQL / Redis / Supervisor restarts
- Disk and memory checks
- Log tail helpers
- Configure site / reinstall agent

Each operation creates a `ServerOperation` row with stdout/stderr retained for support.

## Provider snapshots

On DigitalOcean BYOS servers, snapshot the Droplet from the provider panel or Uplary integration before risky changes. See [Backups](backups.md).

## Managed servers

Managed VPS maintenance windows may be coordinated by the platform operator. Customer-initiated reboots queue like any other operation.

## Related

- [Server operations reference](server-operations.md)
- [Remote management](remote-management.md)
- [Monitoring](monitoring.md)
