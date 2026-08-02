# Remote management

Remote tools are available from each site's detail screen. Mutating actions are persisted before execution and dispatched to the `operations` Horizon queue. The browser polls while work is pending, so navigation does not hold an SSH connection open.

## PHP and Nginx configuration

Customers edit structured settings rather than unrestricted configuration text. This prevents Nginx directives or PHP values from becoming a root-command injection surface.

Each PHP revision generates a dedicated PHP-FPM pool running as `www-data`, with a site-specific Unix socket, memory and upload limits, execution timeout, bounded worker count, and optional error display. `php-fpm -t` must pass before the service reloads. Existing configuration is backed up and restored if validation fails.

Nginx revisions control request size, static-asset caching, and the optional `www` hostname. The generated virtual host retains security headers, protected dotfiles, Laravel front-controller routing, access logs, and the dedicated FPM socket. An active Let's Encrypt certificate is included automatically. Enabling `www` on an SSL site is rejected unless the active certificate contains that hostname. Nginx configuration is backed up and `nginx -t` must pass before reload; a failed test restores the previous file.

Every save creates an immutable encrypted settings revision. Restoring an older revision creates and applies a new version, preserving the audit history.

## File manager boundary

The file manager supports directory listing, text reads and writes, uploads, prepared downloads, directories, rename/move, permission changes, ZIP creation, safe extraction, and recursive deletion. Operations are scoped to `/var/www/{domain}`.

The control plane rejects absolute paths, parent traversal, control characters, and paths longer than 500 characters. The remote script independently resolves the requested path with `realpath` and verifies that the resolved target remains under the site root. This second check blocks traversal through server-side symlinks. ZIP extraction rejects absolute/traversing entries and symbolic-link entries. Archive destinations cannot be nested inside their own source directory.

Text editing is limited to 1 MB. Uploads and prepared downloads are limited to 10 MB. File payloads and operation results are encrypted in the control-plane database. Transfers use a private filesystem disk and are deleted by the scheduler after `REMOTE_TRANSFER_RETENTION_HOURS`, which defaults to 24.

Deletion is recursive but cannot target the site root. The UI requires an explicit confirmation and all deletion still passes the same server-side path boundary.

## Audited command console

The browser console is a queued, non-interactive command runner rather than an unrestricted root terminal. Commands execute from the site's `current` release as `www-data`, have a five-minute remote timeout, retain at most 1 MB of output, and persist encrypted command and output records.

Allowed programs are `php`, `composer`, `git`, `npm`, `node`, `ls`, `pwd`, `cat`, and `tail`. Arguments use a deliberately narrow character set. Shell operators, substitutions, quoting tricks, absolute paths, and parent traversal are rejected before queueing and recompiled again inside the worker. This permits common Laravel maintenance while preventing the web process from constructing a root shell command.

An interactive PTY with WebSockets is intentionally not part of this boundary. It requires a dedicated short-lived session broker, origin checks, connection authorization, terminal recording, and network isolation; it should not reuse the control-plane web workers or root provisioning channel.

## Object storage and large exports

Database exports stream from SSH into a temporary file handle and then to the configured Laravel filesystem disk, avoiding an in-memory SQL string. The project includes the Flysystem S3 adapter. Set `DATABASE_BACKUP_DISK=s3` for exports and imports, or `REMOTE_TRANSFER_DISK=s3` for file-manager transfers, then configure the standard `AWS_*` variables. S3-compatible services can use `AWS_ENDPOINT` and path-style mode.

Each database backup and file transfer records its disk so later configuration changes do not break existing downloads. Database exports expire after `DATABASE_BACKUP_RETENTION_DAYS` (30 by default). Prepared file downloads expire independently after 24 hours by default.

## Production checks

- Run Horizon with the `operations` supervisor and run the Laravel scheduler every minute.
- Confirm `APP_KEY` is stable before saving encrypted settings, commands, or file contents.
- Keep the transfer bucket private and deny public ACLs.
- Use narrowly scoped object-storage credentials and server-side bucket encryption.
- Ensure custom server images provide `sudo`, `zip`, `unzip`, `realpath`, `base64`, Nginx, and the configured PHP-FPM versions.
- Exercise PHP and Nginx apply/rollback against a staging site before customer rollout.
- Run `bash -n resources/scripts/*.sh` and shellcheck in the Linux CI pipeline; Windows environments may not provide a usable Bash runtime.
