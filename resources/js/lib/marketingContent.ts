export type MarketingFlags = {
    managedServersEnabled?: boolean;
    dnsEnabled?: boolean;
    stagingSitesEnabled?: boolean;
    name?: string;
};

export type FeatureItem = { title: string; body: string };
export type FeatureGroup = { title: string; items: FeatureItem[] };

export function featureGroups(flags: MarketingFlags = {}): FeatureGroup[] {
    const name = flags.name || 'the platform';
    const groups: FeatureGroup[] = [
        {
            title: 'Infrastructure',
            items: [
                { title: 'BYOS servers', body: 'Attach DigitalOcean, Hetzner, or a custom Ubuntu VPS over SSH. The console installs nginx, PHP-FPM, and the worker stack on hardware you already pay for.' },
                { title: 'Cloud providers', body: 'Connect a DigitalOcean or Hetzner API token to import existing droplets or create hosts without leaving the console.' },
                { title: 'SSH keys', body: 'Install operator keys on the host from the console. Open a shell whenever you need to — the box is still yours.' },
                { title: 'Remote console', body: 'File manager, nginx settings, and a web terminal on the site — so day-to-day ops do not require a separate SSH client.' },
            ],
        },
        {
            title: 'Applications',
            items: [
                { title: 'Laravel', body: 'Git deploys with composer and npm, zero-downtime releases, env files, migrations, queues, Horizon, and Reverb when the plan allows.' },
                { title: 'WordPress', body: 'Isolated nginx vhost and release root per site — not a crowded shared box. WP-CLI, backups, and SSL live next to the deploy.' },
                { title: 'React / Vite SPAs', body: 'npm build in the release, nginx pointed at the static root. Same SSL, rollback, and logs as a Laravel API on the same host.' },
                { title: 'Git + webhooks', body: 'Connect the repo and branch. Push to deploy, or hit the site webhook. Redeploy from the console without SSHing in to pull.' },
                { title: 'Zero-downtime + rollback', body: 'Build into a new release directory, then atomically switch live. Failed deploys never become production. One click returns the last good release.' },
                { title: 'Live deploy logs', body: 'Watch composer, npm, migrate, and the release switch as they run. Cancel or retry from the same screen.' },
            ],
        },
        {
            title: 'Operations',
            items: [
                { title: 'SSL', body: 'Let’s Encrypt in one click, or upload a custom PEM. Force HTTPS, auto-renew, and remove a certificate without editing nginx by hand.' },
                { title: 'Databases', body: 'Create MySQL databases and users on the server, export/import dumps, and keep credentials out of a shared spreadsheet.' },
                { title: 'Environment & cron', body: 'Edit .env from the console. Schedule cron on the server or the site without logging in to crontab -e.' },
                { title: 'Queues, Horizon, Reverb', body: 'Laravel queue workers, Horizon, and Reverb as plan modules — started and supervised from the site, not a mystery systemd unit.' },
                { title: 'Backups', body: 'Application (code + database), database dumps, and provider OS snapshots. Restore from the same console. Gated by plan.' },
                { title: 'Monitoring', body: 'Uptime and site checks land in the notification bell and optional email. Failed deploys surface the same way.' },
            ],
        },
        {
            title: 'Security',
            items: [
                { title: 'Firewall', body: 'Host-level rules from the console so you are not editing ufw on every box after a deploy.' },
                { title: 'Security scans', body: 'Incidents for scans and suspicious traffic, with actions to block or mark resolved.' },
                { title: 'Two-factor auth', body: 'Protect the console itself. Support impersonation is audited when an administrator has to help.' },
            ],
        },
        {
            title: 'Collaboration',
            items: [
                { title: 'Teams', body: 'Invite operators with roles. Agencies keep a client fleet in one account without sharing a root password.' },
                { title: 'Plans & billing', body: 'Public plans with site, server, and module limits. Stripe checkout when configured — or manual review.' },
                { title: 'Notifications', body: 'In-app bell for deploys, uptime, and security. Email per event when SMTP is set — mute what you do not want in the inbox.' },
            ],
        },
    ];

    if (flags.managedServersEnabled) {
        groups[0].items.splice(1, 0, {
            title: 'Managed servers',
            body: `Launch a platform-hosted size without connecting your own cloud account. ${name} holds the provider token; customers never see it.`,
        });
    }
    if (flags.dnsEnabled !== false) {
        groups[2].items.push({
            title: 'DNS',
            body: 'Zones and records next to the sites they serve. Leave DNS with the registrar if that is already the source of truth.',
        });
    }
    if (flags.stagingSitesEnabled) {
        groups[1].items.push({
            title: 'Staging + promote',
            body: 'A linked staging site on the same server (own vhost and env). Promote repository, branch, script, and PHP version to production when it is ready.',
        });
    }

    return groups;
}

export function homepageFeatures(flags: MarketingFlags = {}): FeatureItem[] {
    return featureGroups(flags).flatMap((group) => group.items);
}

export function platforms(name: string) {
    return [
        {
            title: 'Laravel',
            kicker: 'App platform',
            body: `${name} treats Laravel as a first-class citizen: composer and npm in the release, env files on the server, migrations in the deploy, then an atomic switch. Horizon, Redis workers, and Reverb are plan modules — not extra scripts you paste into supervisor.`,
            points: ['Zero-downtime releases + rollback', 'Queues, Horizon, and Reverb', 'Env, cron, and databases in the console'],
        },
        {
            title: 'WordPress',
            kicker: 'Not shared hosting',
            body: 'Each WordPress site gets its own nginx vhost and release root. WP-CLI, application backups, and Let’s Encrypt sit next to the deploy log — so a plugin update is not five panels and an FTP client.',
            points: ['Isolated vhost per site', 'WP-CLI from the console', 'App + database backups'],
        },
        {
            title: 'React / Vite',
            kicker: 'SPA on your VPS',
            body: 'Build the frontend in the release, point nginx at the static root, and keep a Laravel (or any) API on the same host. Same SSL, webhook, and rollback path as every other site.',
            points: ['npm / Vite as part of deploy', 'Static root + API on one server', 'Git push or console redeploy'],
        },
    ];
}

export function faqs(flags: MarketingFlags = {}): [string, string][] {
    const name = flags.name || 'the platform';
    const items: [string, string][] = [
        ['Which apps can I deploy?', 'Laravel (composer, npm, queues, Horizon, and Reverb when the plan allows), WordPress with WP-CLI, and React/Vite SPAs. Each site has its own nginx vhost, environment, and release history.'],
        ['How do deploys work?', 'Connect a git repo and branch. A push or webhook builds into a new release directory, then switches live. Watch the log. If it fails, roll back to the previous release — production never points at a half-built tree.'],
        ['How does SSL work?', 'Issue Let’s Encrypt from the site, or upload a custom PEM. Force HTTPS, auto-renew, or remove the certificate from the same page. You do not edit nginx by hand for the common case.'],
        ['Do I need to bring my own server?', flags.managedServersEnabled
            ? `Either works. Attach a DigitalOcean, Hetzner, or custom Ubuntu VPS over SSH, or provision a managed size from ${name} when your plan includes it. The cloud bill stays with the provider.`
            : `Yes — ${name} is a control plane for Ubuntu VPS you already own. Connect DigitalOcean, Hetzner, or custom SSH. Your cloud bill stays with that provider.`],
        ['What about staging?', flags.stagingSitesEnabled
            ? 'Create a linked staging site on the same server with its own vhost and env. When it looks right, promote repository, branch, deploy script, and PHP version to production and queue a production deploy.'
            : `Staging is a linked copy on the same server (own nginx vhost and env), then promote to production. The platform operator enables it under settings — ask if you do not see it yet.`],
        ['Can I keep my own DNS and cloud?', flags.dnsEnabled !== false
            ? 'Yes. BYOS means the VPS stays on your account. DNS can live in the console or stay at the registrar. Cloud API tokens you connect are for your droplets, not a lock-in host.'
            : 'Yes. BYOS means the VPS stays on your DigitalOcean, Hetzner, or other account. DNS stays with whoever holds the registrar unless the platform offers it.'],
        ['Databases, cron, and queues?', 'Create MySQL databases and users on the server, edit env vars, schedule cron, and run Laravel queue workers (plus Horizon/Reverb on eligible plans) from the site — not a mystery systemd unit.'],
        ['Can an agency run client sites?', 'Invite teammates with roles, keep SSH keys and deploy logs per site, and stop sharing a root password. Plan limits cap servers and sites; billing is per account.'],
    ];

    return items;
}

export function stackChips(flags: MarketingFlags = {}): string[] {
    const chips = ['Laravel', 'WordPress', 'React / Vite', 'Ubuntu', 'nginx', 'Let’s Encrypt', 'DigitalOcean', 'Hetzner'];
    if (flags.managedServersEnabled) {
        chips.push('Managed VPS');
    }
    return chips;
}
