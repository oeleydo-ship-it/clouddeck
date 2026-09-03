# Databases

Uplary can create and manage **MySQL** and **PostgreSQL** databases on the same server as your application.

## Create a database

1. Open the **server → Databases** tab.
2. Choose engine (MySQL or PostgreSQL), name, and optional user/password.
3. Uplary creates the database and credentials on the host and stores encrypted credentials in the control plane.

## Attach to a site

From the site **Database** tab, attach an on-server database. Uplary syncs `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` into the site's managed environment.

### Managed environment rules

When a managed database is attached:

- `DB_*` variables are **owned by the platform** and cannot be edited on the Environment tab.
- Deploy scripts call `validate_database_env()` and enforce the selected driver.
- SQLite fallbacks are **not** used when MySQL or PostgreSQL is configured.

After changing attachments, **redeploy** so the release `.env` symlink picks up synced values.

## phpMyAdmin

Optional phpMyAdmin can be enabled per server for MySQL administration. Restrict access by IP or disable on production hosts exposed to the internet.

## Backups

Database dumps are included in [site backups](backups.md). Schedule regular backups before major migrations.

## External databases

If your application uses a remote database (RDS, managed PostgreSQL elsewhere), set credentials manually on the Environment tab only when **no** managed database is attached. Do not attach a placeholder on-server database in that case.

## Troubleshooting

| Symptom | Action |
|---------|--------|
| `Access denied` after deploy | Confirm attachment sync; redeploy; check user grants on host |
| Wrong driver (mysql vs pgsql) | Reattach correct database; verify `DB_CONNECTION` in deploy log |
| Migrations connect to SQLite | Managed driver not enforced — upgrade platform and redeploy |
