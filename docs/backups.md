# Automated backups and recovery

Uplary supports recurring SQL recovery points and DigitalOcean Droplet snapshots. Customers manage them from the server **Backups** tab: create policies, **Run now**, pause/enable, download or restore database exports, and create or restore provider snapshots. Every provider or SSH operation runs on the `operations` queue; the scheduler only finds due policies and dispatches work.

## Policies

A policy belongs to one server and creates either a database export or provider snapshot. Schedules can run daily, weekly, or monthly at a local wall-clock time in an IANA timezone. Uplary stores the next execution in UTC and advances it inside a row lock before dispatching, preventing duplicate recovery points when schedulers overlap.

For database policies the customer chooses a private Laravel filesystem disk (local or S3-compatible disks exposed in the form). Retention is count-based per policy. Completed SQL exports beyond the limit are deleted from their recorded filesystem disk and marked expired. Ready exports are also pruned after the configured day-based retention window. Excess provider snapshots are deleted through the cloud API. Removing a policy preserves its existing recovery points.

Run `php artisan schedule:work` during development or invoke `php artisan schedule:run` every minute in production. Horizon must supervise the `operations` queue.

## Database recovery

SQL exports stream from SSH into a temporary stream and then to the configured private Laravel filesystem disk. Each completed export records its byte size and SHA-256 checksum. Local and S3-compatible private disks are supported through `DATABASE_BACKUP_DISK` (and selectable per policy in the UI).

Customers can **Download** a ready export or **Restore database** from the recovery-points list. Restores require the exact database name as confirmation and create a durable restore record. The source recovery point is never deleted by restore. The current SSH import script base64-encodes the SQL payload; very large production databases should use provider-native database backups or a future direct object-storage-to-server streaming agent.

## Provider snapshots

Snapshot creation records the DigitalOcean action, polls it asynchronously, then resolves the provider snapshot ID and size. Customers can create a manual snapshot from the Backups tab or schedule snapshot policies. A server restore requires the exact hostname as confirmation because it replaces the Droplet disk. Uplary polls the restore action and returns the server to `ready` only after the provider reports completion.

Snapshot restore is destructive and should be tested against non-production Droplets first. Application-level shared files, external object storage, managed databases, DNS, and provider resources outside the Droplet are not restored by a Droplet snapshot.

## BYO / custom servers

Custom (bring-your-own) servers support database export policies only. Provider snapshots require a Droplet `provider_id`. The Backups UI surfaces this limit when the server has no cloud provider ID.

## Failure alerts

Failed database exports, provider snapshot create/refresh failures, and database restore failures notify the owner through `OperationalEventNotification` (`backup_failed`), the same event WordPress on-server backups already use. Customers subscribe under **Notifications → Email recipients**.

## API

Sanctum clients can list recovery state at `GET /api/backups`, create policies at `POST /api/servers/{server}/backup-policies`, queue a run at `POST /api/backup-policies/{policy}/run`, and remove policies with `DELETE /api/backup-policies/{policy}`. Read operations require `servers:read`; mutations require `servers:write`.

## Security and operations

- Every web and API action authorizes ownership through the server policy.
- Database and server restores require explicit resource-name confirmation.
- Restore requests and policy changes are written to the encrypted administrative audit trail.
- Provider credentials, database passwords, and stored configuration remain encrypted at rest.
- Review Horizon failures for jobs that never reach `failed()` (worker crashes, timeout kills).
- Large SQL restores still base64 the payload over SSH; very large production databases should use provider-native backups or a future streaming agent.
- WordPress site archives remain on the VPS (not downloaded/offloaded to S3 from this panel).
- Encrypted off-site archives, Laravel site file backups, and restore verification drills remain out of scope.
