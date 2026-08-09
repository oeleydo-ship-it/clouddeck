# Automated backups and recovery

Uplary supports recurring **database backups** (SQL exports), **OS backups** (provider Droplet snapshots), and **site backups** (full application code + database archives streamed over SSH). Database and OS backups are separate plan entitlements. Site backups use the `site_backups` feature (“Site backups (code + database)”). **OS backup storage** is also capacity-limited: plans include an `os_backup_gb` quota, and customers can buy extra GB as a Stripe add-on on Billing. Off-server archives use the private Laravel disk selected as default in **Admin → Storage** (`local` or S3-compatible object storage).

## Policies

A policy belongs to one server and creates either a database export or provider snapshot (OS backup). Schedules can run daily, weekly, or monthly at a local wall-clock time in an IANA timezone. Uplary stores the next execution in UTC and advances it inside a row lock before dispatching, preventing duplicate recovery points when schedulers overlap.

For database policies the customer chooses a private Laravel filesystem disk (local or S3-compatible disks exposed in the form). Retention is count-based per policy. Completed SQL exports beyond the limit are deleted from their recorded filesystem disk and marked expired. Ready exports are also pruned after the configured day-based retention window. Excess provider snapshots are deleted through the cloud API. Removing a policy preserves its existing recovery points.

Run `php artisan schedule:work` during development or invoke `php artisan schedule:run` every minute in production. Horizon must supervise the `operations` queue.

## Database recovery

SQL exports stream from SSH into a temporary stream and then to the configured private Laravel filesystem disk. Each completed export records its byte size and SHA-256 checksum. Local and S3-compatible private disks are supported through `DATABASE_BACKUP_DISK` (and selectable per policy in the UI).

Customers can **Download** a ready export or **Restore database** from the recovery-points list. Restores require the exact database name as confirmation and create a durable restore record. The source recovery point is never deleted by restore. The current SSH import script base64-encodes the SQL payload; very large production databases should use provider-native database backups or a future direct object-storage-to-server streaming agent.

## OS backups (provider snapshots)

Snapshot creation records the DigitalOcean action, polls it asynchronously, then resolves the provider snapshot ID and size. Customers can create a manual snapshot from the Backups tab or schedule snapshot policies when **OS backups** is on their plan. A server restore requires the exact hostname as confirmation because it replaces the Droplet disk. Uplary polls the restore action and returns the server to `ready` only after the provider reports completion.

Snapshot restore is destructive and should be tested against non-production Droplets first. Application-level shared files, external object storage, managed databases, DNS, and provider resources outside the Droplet are not restored by a Droplet snapshot.

## Site backups (code + database)

Per-site **full application backups** pack the live `current` release tree, `shared` (`.env`, storage / `wp-content`), and a SQL dump of the linked managed database (or WP-CLI / mysqldump for WordPress). The archive is streamed over SSH into the configured private Laravel disk — **local** (`storage/app/private`) or **S3-compatible object storage** (DigitalOcean Spaces, Hetzner, Wasabi, etc.) so recovery survives losing the VPS. Superadmins configure credentials and the default disk under **Admin → Storage**. This path works on **custom IP-only servers** the same as cloud Droplets — no provider snapshot is required.

Open any site → **Backups** tab → **Create full backup**. Ready points support **Download**, **Restore** (type the exact domain), and **Delete**. Restores replace the live application files and import the SQL dump. Site backups are gated by the `site_backups` plan feature (off on Free; on for Pro/Business by default).

WordPress sites keep a separate **On-server WordPress backup** section under the same tab for local VPS archives of the database and `wp-content`. Those remain on the server and are not replaced by full-app offloads.

## BYO / custom servers

Custom (bring-your-own) servers support database backup policies and **site (code + database) backups**. OS / provider snapshots require a Droplet `provider_id`. The server Backups UI surfaces the snapshot limit when the server has no cloud provider ID; site backups live on each site’s Backups tab.

## Failure alerts

Failed database exports, provider snapshot create/refresh failures, database restore failures, and full site backup/restore failures notify the owner through `OperationalEventNotification` (`backup_failed`), the same event WordPress on-server backups already use. Customers subscribe under **Notifications → Email recipients**.

## API

Sanctum clients can list recovery state at `GET /api/backups`, create policies at `POST /api/servers/{server}/backup-policies`, queue a run at `POST /api/backup-policies/{policy}/run`, and remove policies with `DELETE /api/backup-policies/{policy}`. Read operations require `servers:read`; mutations require `servers:write`. Listing and mutating require the matching plan feature (`database_backups` and/or `os_backups`).

## Security and operations

- Every web and API action authorizes ownership through the server or site policy.
- Database, server, and full site restores require explicit resource-name confirmation.
- Restore requests and policy changes are written to the encrypted administrative audit trail.
- Provider credentials, database passwords, and stored configuration remain encrypted at rest.
- Review Horizon failures for jobs that never reach `failed()` (worker crashes, timeout kills).
- Large SQL restores still base64 the payload over SSH; very large production databases should use provider-native backups or a future streaming agent.
- On-server WordPress archives remain on the VPS; full site archives are offloaded to the configured private disk.
- Encrypted off-site archives beyond the configured disk, metered GB billing for site archives, and restore verification drills remain out of scope for this pass.
