<?php

namespace App\Http\Middleware;

use App\Enums\DeploymentStatus;
use App\Models\AlertIncident;
use App\Models\Deployment;
use App\Models\SecurityIncident;
use App\Models\SiteMonitorIncident;
use App\Services\FeatureManager;
use App\Services\ImpersonationManager;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Throwable;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $settings = app(SystemSettings::class);
        $user = $request->user();
        $impersonation = app(ImpersonationManager::class);
        $onMarketing = $request->routeIs('home', 'about', 'features', 'use-cases', 'blog', 'blog.show', 'contact');

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'download_key' => fn () => $request->session()->get('download_key'),
                'database_password' => fn () => $request->session()->get('database_password'),
                'monitoring_secret' => fn () => $request->session()->get('monitoring_secret'),
                'recovery_codes' => fn () => $request->session()->get('recovery_codes'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                ] : null,
            ],
            'branding' => $settings->branding(),
            'features' => $user ? app(FeatureManager::class)->mapFor($user) : [],
            'dnsEnabled' => $settings->dnsEnabled(),
            'publicSiteEnabled' => $settings->publicSiteEnabled(),
            'managedServersReady' => $settings->managedServersReady(),
            'supportEmail' => $settings->get('support_email'),
            'seo' => $settings->seo(),
            'analytics' => $settings->analytics(),
            'aiGuideEnabled' => $user ? $settings->aiGuideEnabled() : false,
            'insertCode' => $this->visibleInsertCode($settings->insertCode(), $onMarketing, (bool) $user),
            'onMarketing' => $onMarketing,
            'shellAlerts' => fn () => $this->shellAlerts($user),
            'impersonation' => [
                'active' => $impersonation->isImpersonating(),
                'support_mode' => $impersonation->supportMode(),
                'support_mode_label' => $impersonation->supportMode() === 'read_only' ? 'Read only' : ($impersonation->supportMode() === 'full' ? 'Full' : null),
                'banner' => $impersonation->isImpersonating() && $user ? 'You are impersonating '.$user->name : null,
                'exit_label' => $impersonation->isImpersonating() ? 'Exit impersonation' : null,
                'target' => $impersonation->isImpersonating() && $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'chrome' => [
                'billing' => 'Billing',
                'sign_out' => 'Sign out',
                'teams' => 'Teams',
                'account' => 'Account settings',
                'documentation' => 'Documentation',
                'contact' => 'Contact',
                'open_console' => 'Open console',
                'view_website' => 'View website',
                'primary_nav' => 'Primary',
                'billing_href' => '/billing',
                'teams_href' => '/teams',
                'account_href' => '/account',
                'home_href' => route('home'),
            ],
            'consoleNav' => $user ? $this->consoleNav($request, $settings) : [],
            'adminNav' => $user?->isSuperAdmin()
                ? collect(require resource_path('views/partials/admin-nav-sections.php'))
                    ->map(fn (array $section) => [
                        'href' => route($section['route']),
                        'label' => $section['label'],
                        'route' => $section['route'],
                        'icon' => $section['icon'],
                    ])->values()->all()
                : [],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Keep unused insert snippets out of the page JSON so console/marketing toggles
     * can be asserted against the rendered HTML instead of leftover props.
     *
     * @param  array{head: mixed, body: mixed, on_marketing: bool, on_console: bool}  $insert
     * @return array{head: mixed, body: mixed, on_marketing: bool, on_console: bool}
     */
    private function visibleInsertCode(array $insert, bool $onMarketing, bool $authenticated): array
    {
        $inConsole = $authenticated && ! $onMarketing;
        $inject = $inConsole ? (bool) ($insert['on_console'] ?? false) : (bool) ($insert['on_marketing'] ?? true);

        if (! $inject) {
            $insert['head'] = null;
            $insert['body'] = null;
        }

        return $insert;
    }

    /**
     * @return list<array{href: string, label: string, icon: string, match: string, locked?: bool}>
     */
    private function consoleNav(Request $request, SystemSettings $settings): array
    {
        $features = app(FeatureManager::class)->mapFor($request->user());
        $can = fn (string $key) => (bool) ($features[$key] ?? false);
        $items = [
            ['href' => route('dashboard'), 'label' => 'Dashboard', 'match' => 'dashboard', 'icon' => 'M3 3h7v7H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 14h7v7H3z'],
            ['href' => route('servers.index'), 'label' => 'Servers', 'match' => 'servers', 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01'],
            ['href' => route('sites.index'), 'label' => 'Sites', 'match' => 'sites', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z'],
            ['href' => route('firewall.index'), 'label' => 'Firewall', 'match' => 'firewall', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9.5 12h5M12 9.5v5', 'locked' => ! $can('firewall')],
            ['href' => route('security.index'), 'label' => 'Security', 'match' => 'security', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9 12l2 2 4-4', 'locked' => ! $can('security')],
            ['href' => route('notifications.index'), 'label' => 'Notifications', 'match' => 'notifications', 'icon' => 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9ZM10.3 21a1.94 1.94 0 0 0 3.4 0', 'locked' => ! $can('notifications')],
            ['href' => route('cloud-accounts'), 'label' => 'Providers', 'match' => 'cloud-accounts', 'icon' => 'M17.5 19a4.5 4.5 0 0 0 .5-8.97A6 6 0 0 0 6.2 9.4 4.5 4.5 0 0 0 6.5 19h11Z', 'locked' => ! $can('providers')],
        ];
        if ($settings->dnsEnabled()) {
            $items[] = ['href' => route('dns.index'), 'label' => 'DNS', 'match' => 'dns', 'icon' => 'M4 6h16M4 12h16M4 18h10M18 15l3 3-3 3', 'locked' => ! $can('dns')];
        }
        $items[] = ['href' => route('ssh-keys'), 'label' => 'SSH keys', 'match' => 'ssh-keys', 'icon' => 'M15 7a5 5 0 1 1-4.9 6H7v3H4v-3H2v-3h8.1A5 5 0 0 1 15 7Zm2 4h.01', 'locked' => ! $can('ssh')];
        if ($request->user()->isSuperAdmin()) {
            $items[] = ['href' => route('admin.dashboard'), 'label' => 'Admin', 'match' => 'admin', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 0v4'];
        }

        return $items;
    }

    /**
     * @return array<int, array{id?: string, title: string, description: string, href: string, tone: string, unread?: bool}>
     */
    private function shellAlerts($user): array
    {
        if (! $user) {
            return [];
        }

        try {
            $database = Schema::hasTable('notifications')
                ? $user->unreadNotifications()->latest()->limit(8)->get()->map(fn (DatabaseNotification $notification) => $this->presentDatabaseAlert($notification))
                : collect();

            $incidents = Schema::hasTable('alert_incidents')
                ? AlertIncident::query()
                    ->with('server')
                    ->where('status', 'open')
                    ->whereHas('server', fn ($query) => $query->accessibleTo($user))
                    ->latest('started_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (AlertIncident $incident) => [
                        'title' => Str::headline($incident->metric).' alert',
                        'description' => ($incident->server?->name ?? 'Server').' · '.$incident->started_at?->diffForHumans(),
                        'href' => $incident->server ? route('servers.manage', $incident->server) : route('servers.index'),
                        'tone' => $incident->severity === 'critical' ? 'danger' : 'warning',
                        'unread' => true,
                    ])
                : collect();

            $securityIncidents = Schema::hasTable('security_incidents')
                ? SecurityIncident::query()
                    ->accessibleTo($user)
                    ->with('server')
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->latest('last_seen_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (SecurityIncident $incident) => [
                        'title' => 'Security: '.$incident->title,
                        'description' => ($incident->server?->name ?? 'Server').' · '.$incident->last_seen_at?->diffForHumans(),
                        'href' => route('notifications.index', ['tab' => 'incidents', 'type' => 'security']),
                        'tone' => $incident->severity === 'critical' ? 'danger' : 'warning',
                        'unread' => true,
                    ])
                : collect();

            $siteIncidents = Schema::hasTable('site_monitor_incidents')
                ? SiteMonitorIncident::query()
                    ->with('site:id,domain')
                    ->where('status', 'open')
                    ->whereHas('site.server', fn ($query) => $query->accessibleTo($user))
                    ->latest('started_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (SiteMonitorIncident $incident) => [
                        'title' => $incident->message ?: 'Site monitor alert',
                        'description' => ($incident->site?->domain ?? 'Site').' · '.$incident->started_at?->diffForHumans(),
                        'href' => $incident->site_id
                            ? route('sites.show', ['site' => $incident->site_id]).'?tab=monitoring'
                            : route('notifications.index', ['tab' => 'incidents', 'type' => 'site']),
                        'tone' => $incident->type === 'site_down' ? 'danger' : 'warning',
                        'unread' => true,
                    ])
                : collect();

            $deployments = Schema::hasTable('deployments')
                ? Deployment::query()
                    ->with('site')
                    ->where('status', DeploymentStatus::Failed)
                    ->whereHas('site.server', fn ($query) => $query->accessibleTo($user))
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Deployment $deployment) => [
                        'title' => 'Deployment failed',
                        'description' => ($deployment->site?->domain ?? 'Site').' · '.$deployment->created_at?->diffForHumans(),
                        'href' => route('deployments.show', $deployment),
                        'tone' => 'danger',
                        'unread' => true,
                    ])
                : collect();

            return $database
                ->concat($securityIncidents)
                ->concat($incidents)
                ->concat($siteIncidents)
                ->concat($deployments)
                ->unique(fn (array $row) => ($row['href'] ?? '').'|'.($row['title'] ?? ''))
                ->take(8)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array{id: string, title: string, description: string, href: string, tone: string, unread: bool}
     */
    private function presentDatabaseAlert(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = $data['title'] ?? $data['message'] ?? Str::headline(class_basename($notification->type));
        $body = $data['body'] ?? (isset($data['domain']) ? $data['domain'] : null);
        if (is_string($body) && $body === $title) {
            $body = null;
        }

        return [
            'id' => (string) $notification->id,
            'title' => $title,
            'description' => trim(($body ? Str::limit(strip_tags($body), 90).' · ' : '').($notification->created_at?->diffForHumans() ?? '')),
            'href' => $this->notificationHref($data),
            'tone' => in_array($data['severity'] ?? $data['status'] ?? '', ['critical', 'failed', 'danger'], true) ? 'danger' : 'warning',
            'unread' => $notification->read_at === null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notificationHref(array $data): string
    {
        if (! empty($data['url']) && is_string($data['url'])) {
            return $data['url'];
        }
        if (! empty($data['server_id'])) {
            return route('servers.manage', $data['server_id']);
        }
        if (! empty($data['deployment_id'])) {
            return route('deployments.show', $data['deployment_id']);
        }
        if (! empty($data['site_id'])) {
            return route('sites.show', $data['site_id']);
        }
        if (! empty($data['invoice_id'])) {
            return route('billing.index');
        }

        return route('notifications.index');
    }
}
