# Account security

Protect your Uplary account, sessions, and automation credentials.

## Password

Change your password from **Account → Security**. Use a unique password stored in a password manager.

## Two-factor authentication (2FA)

Enable TOTP-based 2FA with an authenticator app. Recovery codes are shown once — store them offline. 2FA is required for sensitive actions when enforced by policy.

## Sessions

Review active sessions and revoke unknown devices from the security settings page. Signing out elsewhere invalidates other session cookies.

## API tokens

Each token is tied to your user and optional abilities. Rotate tokens periodically and delete unused tokens. Tokens bypass 2FA for API calls — treat them like passwords.

## SSH keys

See [SSH keys](ssh-keys.md). Compromise of a Uplary-managed private key grants host access independent of your web password.

## Provider tokens

DigitalOcean and Cloudflare tokens stored in Uplary are encrypted. Revoke at the provider immediately if leaked, then remove and re-add in Uplary.

## Reporting incidents

If you suspect unauthorized access, rotate passwords, revoke API tokens and sessions, and contact support with timestamps and affected resources.
