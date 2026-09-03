<?php

namespace App\Support;

use Illuminate\Support\Str;

final class DocumentationCatalog
{
    /**
     * @return list<array{id: string, title: string, html: string}>
     */
    public static function sections(bool $managedServersEnabled, bool $stagingSitesEnabled): array
    {
        $catalog = [
            ['id' => 'getting-started', 'title' => 'Getting started', 'file' => 'getting-started.md'],
            ['id' => 'whats-new', 'title' => "What's new", 'file' => 'whats-new.md'],
            ['id' => 'managed-servers', 'title' => 'Managed servers', 'file' => 'managed-servers.md', 'when' => $managedServersEnabled],
            ['id' => 'providers', 'title' => 'Providers & IPs', 'file' => 'providers-and-ips.md'],
            ['id' => 'ssh-keys', 'title' => 'SSH keys', 'file' => 'ssh-keys.md'],
            ['id' => 'provisioning', 'title' => 'Provisioning (BYOS)', 'file' => 'provisioning.md'],
            ['id' => 'sites', 'title' => 'Adding a site', 'file' => 'sites.md'],
            ['id' => 'deployments', 'title' => 'Deployments', 'file' => 'deployment-engine.md'],
            ['id' => 'ssl', 'title' => 'SSL certificates', 'file' => 'ssl-certificates.md'],
            ['id' => 'databases', 'title' => 'Databases', 'file' => 'databases.md'],
            ['id' => 'workers', 'title' => 'Workers & cron', 'file' => 'workers-and-cron.md'],
            ['id' => 'monitoring', 'title' => 'Monitoring', 'file' => 'monitoring.md'],
            ['id' => 'security-detection', 'title' => 'Security detection', 'file' => 'security-detection.md'],
            ['id' => 'notifications', 'title' => 'Notifications', 'file' => 'notifications.md'],
            ['id' => 'firewall', 'title' => 'Firewall', 'file' => 'firewall.md'],
            ['id' => 'backups', 'title' => 'Backups', 'file' => 'backups.md'],
            ['id' => 'staging', 'title' => 'Staging sites', 'file' => 'staging-sites.md', 'when' => $stagingSitesEnabled],
            ['id' => 'remote', 'title' => 'Remote management', 'file' => 'remote-management.md'],
            ['id' => 'maintenance', 'title' => 'Server maintenance', 'file' => 'server-maintenance.md'],
            ['id' => 'dns', 'title' => 'DNS', 'file' => 'dns.md'],
            ['id' => 'teams', 'title' => 'Teams & API', 'file' => 'teams-and-api.md'],
            ['id' => 'plans', 'title' => 'Plans & billing', 'file' => 'stripe-billing.md'],
            ['id' => 'password', 'title' => 'Account security', 'file' => 'account-security.md'],
            ['id' => 'support', 'title' => 'Getting help', 'file' => 'getting-help.md'],
        ];

        return collect($catalog)
            ->filter(fn (array $section) => ($section['when'] ?? true) === true)
            ->map(function (array $section): array {
                $path = base_path('docs/'.$section['file']);
                $markdown = is_file($path) ? (string) file_get_contents($path) : '_Documentation file missing._';

                return [
                    'id' => $section['id'],
                    'title' => $section['title'],
                    'html' => Str::markdown($markdown),
                ];
            })
            ->values()
            ->all();
    }
}
