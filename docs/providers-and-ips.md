# Providers and IP addresses

Uplary supports two ways to add infrastructure: **cloud provider integration** and **bring-your-own-server (BYOS) by IP**.

## DigitalOcean

1. Create a **Personal Access Token** in the DigitalOcean control panel with read/write scope.
2. In Uplary, open **Servers → Providers → DigitalOcean** and paste the token.
3. The token is stored encrypted. Uplary uses it to create Droplets, read sizes/regions, and optionally manage snapshots.

When provisioning a Droplet, you select region, size, and the SSH key registered in Uplary. The new server's public IPv4 appears on the server detail page once creation completes.

## Attach by IP (any Ubuntu VPS)

Use this for Contabo, Hetzner, Linode, or any host you already operate:

1. **Servers → Add server → Existing server**.
2. Enter the public **IPv4**, **SSH user** (usually `root`), and select the SSH key Uplary will use.
3. Uplary connects over SSH and runs the bootstrap script.

Requirements:

- Ubuntu 22.04 or 24.04 recommended
- Port 22 reachable from the control plane
- Root or sudo-capable user
- Fresh or lightly customized image (conflicting web stacks may need manual cleanup)

## Public IP and DNS

Each ready server displays its **public IP**. Point site A/AAAA records (or Cloudflare proxied records) at this address unless you use a load balancer in front.

For Cloudflare **proxied** orange-cloud records, ensure the origin has a valid HTTPS vhost for each site domain — otherwise Cloudflare may hit the wrong default SSL site on port 443.

## Provider token security

Tokens and SSH private keys use Laravel encrypted casts. Rotate provider tokens in both the cloud dashboard and Uplary if compromised. Never commit tokens to Git or paste them into site environment variables unless the application explicitly requires them.

## Related

- [SSH keys](ssh-keys.md)
- [Provisioning](provisioning.md)
- [DNS](dns.md)
