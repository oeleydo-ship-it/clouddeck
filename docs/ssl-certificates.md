# SSL certificates

Uplary manages TLS for each site from the site **SSL** tab.

## Certificate types

| Type | When to use |
|------|-------------|
| **Let's Encrypt** | Public sites with HTTP-01 or DNS validation reachable from the internet |
| **Custom SSL** | Cloudflare Origin certificates, corporate CAs, or pre-generated PEM bundles |
| **None** | Remove SSL to serve HTTP only while reconfiguring |

## Let's Encrypt

1. Ensure DNS points to the server (or Cloudflare proxies to the correct origin).
2. Port **80** must reach Uplary's HTTP challenge location, or use DNS validation if configured.
3. Click **Issue certificate** or rely on automatic queueing after configure + successful deploy (`QueueSiteSslIssuance`).

Certificates renew automatically before expiry when the site remains active and challenges succeed.

## Cloudflare proxied domains

When Cloudflare connects to origin on **port 443**, nginx must have an HTTPS server block for that hostname. Uplary's configure script installs a temporary self-signed vhost on :443 so proxied traffic does not fall through to another site's default certificate.

For **Full (strict)** mode, install a **Custom SSL** certificate matching Cloudflare's origin CA or your uploaded origin PEM.

### Custom SSL upload

1. In Cloudflare: **SSL/TLS → Origin Server → Create certificate**.
2. Copy the **Origin Certificate** and **Private Key** PEMs.
3. In Uplary: **SSL → Custom → Install**, paste both PEMs.
4. Validation ensures the key matches the certificate (OpenSSL 3 compatible).

If a previous install failed with cipher/modulus errors, **Remove SSL** and reinstall with fresh PEMs after upgrading to a platform version that includes the fix.

## Remove SSL

**Remove SSL** deletes the active certificate configuration and returns the vhost to HTTP. Use this before switching from custom to Let's Encrypt or when debugging vhost issues.

## Troubleshooting

| Issue | Likely cause |
|-------|----------------|
| Wrong site content on HTTPS | Missing vhost for hostname; default SSL site wins — re-run **Configure site** |
| LE challenge failed | DNS not propagated, port 80 blocked, or Cloudflare SSL mode blocking HTTP |
| Certificate mismatch | Custom PEM does not match domain or key pair |
| 502 after SSL change | PHP-FPM or nginx syntax error — check site operation logs |

## Related

- [DNS](dns.md)
- [Adding a site](sites.md)
