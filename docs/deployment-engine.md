# Deployment engine

## Remote layout

Each site is isolated under `/var/www/<domain>`:

```text
/var/www/app.example.com/
├── current -> releases/20260801143000-ab12cd34
├── releases/
│   └── 20260801143000-ab12cd34/
└── shared/
    ├── .env
    └── storage/
```

Nginx always serves `current/public`. A deployment is built completely in a new release directory. After dependencies, migrations, assets, custom commands, and Laravel caches succeed, `current.next` is created and renamed over `current` atomically. The five newest release directories are retained.

## Queue lifecycle

Manual, API, and webhook triggers create a `deployments` row before dispatching `DeployLaravelJob` to the `deployments` queue. Only one pending or running deployment may exist for a site. Status, progress, timestamps, release identifiers, commit metadata, exit code, and command output are persisted.

Provisioning and site configuration use the `provisioning` queue. Deployment notifications use the `notifications` queue. Production should run separate Horizon supervisors so long-running builds cannot starve interactive work.

## Secrets

Environment values, provider tokens, TOTP secrets, and managed SSH private keys use Laravel encrypted casts. Deployment scripts are passed to SSH over standard input, keeping environment payloads out of local and remote process argument lists. Temporary private-key files are mode `0600` and removed in a `finally` block.

Authorized users can view decrypted environment values in the site editor. Application logs and deployment scripts must never print secrets.

## Repository URLs

Laravel sites accept provider-agnostic clone URLs:

- HTTPS: `https://github.com/acme/app.git`, `https://gitlab.com/group/subgroup/app.git`, `https://bitbucket.org/workspace/app.git`
- SCP-style SSH: `git@gitlab.com:group/app.git`, `git@bitbucket.org:workspace/app.git`
- URI-style SSH: `ssh://git@bitbucket.org/workspace/app.git`

Private clones require Git credentials (deploy key or token) already available on the managed server.

## Webhooks

The endpoint is displayed on the site's Webhook tab.

- GitHub: `X-Hub-Signature-256: sha256=<HMAC>` over the exact raw request body.
- Bitbucket Cloud: `X-Hub-Signature: sha256=<HMAC>` over the exact raw request body.
- GitLab: send the site's secret as `X-Gitlab-Token`.
- Custom integrations may use `X-Uplary-Signature` with the same SHA-256 HMAC format.

Only the configured branch is accepted. Deleted-branch pushes (all-zero commit hash) and previously queued, running, or successful commit hashes are ignored. Webhook endpoints are exempt from CSRF protection but remain signature-validated and rate-limited.

## Failure and rollback

An error before the atomic switch removes only the incomplete release. The previously active release remains untouched. Failed deployments retain logs and exit codes. Rollback is a queued atomic switch to one of the five retained successful releases followed by PHP-FPM, Nginx, and queue-worker reloads.

## Worker configuration

Recommended queue groups:

```text
provisioning: 1 process, timeout 1800s, tries 1
deployments: 2 processes, timeout 1800s, tries 1
notifications: 2 processes, timeout 60s, tries 3
default: 2 processes
```

Use Redis in production. Ensure the control-plane worker has the OpenSSH client and can reach managed hosts on TCP port 22. Managed servers need Git credentials for private repository clones.
