<?php

namespace App\Http\Controllers\Admin;

use App\Cloud\CloudProviderManager;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingRequest;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FeatureManager;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Each administrative section is its own page rather than a tab in one document. The tabbed
 * version loaded every customer, plan, flag, billing request, and audit row on every visit,
 * and a refresh always dropped the operator back on the first tab.
 */
class AdminDashboardController extends Controller
{
    public function overview(): Response
    {
        return Inertia::render('Admin/Overview', [
            'title' => 'Overview · SaaS control center',
            'stats' => [
                'users' => User::count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
                'billing_requests' => BillingRequest::where('status', 'pending')->count(),
            ],
            'auditLogs' => AuditLog::with('actor')->latest()->limit(10)->get(),
        ]);
    }

    public function users(Request $request): Response
    {
        $users = User::with('currentSubscription.plan')
            ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                ...$user->toArray(),
                'status_label' => $user->suspended_at ? 'Suspended' : null,
                'show_url' => route('admin.users.show', $user),
            ]);

        return Inertia::render('Admin/Users', [
            'title' => 'Users',
            'users' => $users,
            'plans' => Plan::orderBy('sort_order')->get(),
            'emptySearch' => $request->filled('search') && $users->total() === 0 ? 'No accounts match that search' : null,
            'copy' => ['Impersonate user', 'Restore', 'Suspend', 'Suspended'],
        ]);
    }

    public function plans(): Response
    {
        return Inertia::render('Admin/Plans', [
            'title' => 'Plans',
            'featureCatalog' => FeatureManager::catalog(),
            'plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->get()->map(fn (Plan $plan) => [
                ...$plan->toArray(),
                'monthly_price_label' => $plan->formattedPrice('monthly_price'),
                'yearly_price_label' => $plan->yearly_price ? $plan->formattedPrice('yearly_price') : null,
                'subscriptions_label' => $plan->subscriptions_count.' subscriptions',
                'unlimited' => collect($plan->limits ?? [])->contains(fn ($limit) => $limit < 0) ? 'Unlimited' : null,
                'feature_labels' => $plan->enabledFeatureLabels(),
            ]),
        ]);
    }

    public function features(): Response
    {
        return Inertia::render('Admin/Features', [
            'title' => 'Feature flags',
            'empty' => FeatureFlag::count() === 0 ? 'No feature flags' : null,
            'flags' => FeatureFlag::orderBy('key')->get()->map(fn (FeatureFlag $flag) => [
                ...$flag->toArray(),
                'rollout_label' => $flag->enabled
                    ? $flag->rollout_percentage.'% of customers'
                    : 'Off for everyone',
            ]),
        ]);
    }

    public function billing(): Response
    {
        return Inertia::render('Admin/Billing', ['title' => 'Billing review', 'billingRequests' => BillingRequest::with(['user', 'plan'])->where('status', 'pending')->latest()->get()]);
    }

    public function payments(): Response
    {
        return Inertia::render('Admin/Payments', [
            'title' => 'Payments',
            'plans' => Plan::orderBy('sort_order')->get(),
            'settings' => SystemSetting::all()->keyBy('key'),
            'stripeLabel' => 'Stripe API',
            'webhookUrl' => url('/api/billing/stripe/webhook'),
            'keySaved' => filled(app(SystemSettings::class)->get('stripe_key')),
            'secretSaved' => filled(app(SystemSettings::class)->get('stripe_secret')),
            'webhookSaved' => filled(app(SystemSettings::class)->get('stripe_webhook_secret')),
        ]);
    }

    public function storage(): Response
    {
        return Inertia::render('Admin/Storage', [
            'title' => 'Object storage',
            'providerHint' => 'DigitalOcean Spaces',
            'settings' => SystemSetting::all()->keyBy('key'),
            'objectStorage' => app(SystemSettings::class)->objectStorage(),
            'databaseBackupDisk' => app(SystemSettings::class)->databaseBackupDisk(),
        ]);
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings', ['title' => 'Settings', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function mail(): Response
    {
        return Inertia::render('Admin/Mail', ['title' => 'SMTP', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function notifications(): Response
    {
        $system = app(SystemSettings::class);

        return Inertia::render('Admin/Notifications', [
            'title' => 'Notification center',
            'masterLabel' => 'Send operational alert emails',
            'settings' => SystemSetting::all()->keyBy('key'),
            'clientEmailEnabled' => $system->clientEmailNotificationsEnabled(),
            'eventToggles' => $system->clientEmailEventToggles(),
            'eventLabels' => \App\Models\NotificationChannel::EVENTS,
            'billingFailedAllowed' => $system->clientEmailBillingFailedAllowed(),
        ]);
    }

    public function managedServers(CloudProviderManager $providers, SystemSettings $systemSettings): Response
    {
        $ready = $systemSettings->managedServersReady();
        $managedSizes = [];
        if ($ready) {
            try {
                $managedSizes = collect($providers->forPlatform()->sizes())->sortBy('price_monthly')->values()->all();
            } catch (Throwable) {
                $managedSizes = [];
            }
        }

        return Inertia::render('Admin/ManagedServers', [
            'title' => 'Managed servers',
            'settings' => SystemSetting::all()->keyBy('key'),
            'ready' => $ready,
            'tokenSaved' => filled($systemSettings->managedCloudToken()),
            'managedSizes' => collect($managedSizes)->map(function (array $size) use ($systemSettings) {
                $memoryMb = (int) ($size['memory'] ?? 0);
                $vcpus = (int) ($size['vcpus'] ?? 0);
                $disk = $size['disk'] ?? null;
                $infra = (float) ($size['price_monthly'] ?? 0);
                $ram = $memoryMb >= 1024
                    ? rtrim(rtrim(number_format($memoryMb / 1024, 1, '.', ''), '0'), '.').' GB RAM'
                    : ($memoryMb > 0 ? $memoryMb.' MB RAM' : null);
                $spec = collect([
                    $vcpus > 0 ? $vcpus.' vCPU' : null,
                    $ram,
                    is_numeric($disk) && (float) $disk > 0 ? $disk.' GB disk' : null,
                ])->filter()->implode(' · ');

                return [
                    'slug' => (string) ($size['slug'] ?? ''),
                    'description' => $size['description'] ?? $size['name'] ?? null,
                    'vcpus' => $vcpus,
                    'memory' => $memoryMb,
                    'disk' => $disk,
                    'price_monthly' => $infra,
                    'spec' => $spec,
                    'infra_label' => $infra > 0 ? '$'.number_format($infra, 2).'/mo' : null,
                    'suggested' => round($infra * (1 + $systemSettings->managedMarkupPercent() / 100), 2),
                ];
            })->values()->all(),
            'managedMarkupPercent' => $systemSettings->managedMarkupPercent(),
            'managedSizePrices' => $systemSettings->managedSizePrices(),
        ]);
    }

    public function pages(): Response
    {
        return Inertia::render('Admin/Pages', ['title' => 'Pages', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function seo(): Response
    {
        return Inertia::render('Admin/Seo', ['title' => 'SEO', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function analytics(): Response
    {
        return Inertia::render('Admin/Analytics', ['title' => 'Analytics', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function webmaster(): Response
    {
        return Inertia::render('Admin/Webmaster', ['title' => 'Webmaster', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function ai(): Response
    {
        return Inertia::render('Admin/Ai', ['title' => 'AI', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function googleAuth(): Response
    {
        $secretSaved = filled(app(SystemSettings::class)->get('google_client_secret'));
        $idSaved = filled(app(SystemSettings::class)->get('google_client_id'));

        return Inertia::render('Admin/GoogleAuth', [
            'title' => 'Google Auth',
            'settings' => SystemSetting::all()->keyBy('key'),
            'callbackUrl' => url('/auth/google/callback'),
            'enableLabel' => 'Enable Google sign-in',
            'secretSaved' => $secretSaved,
            'idSaved' => $idSaved,
            'secretPlaceholder' => $secretSaved ? 'Saved — leave blank to keep it' : 'GOCSPX-...',
            'idPlaceholder' => $idSaved ? 'Using .env — paste to store in settings' : 'xxxx.apps.googleusercontent.com',
        ]);
    }

    public function insertCode(): Response
    {
        return Inertia::render('Admin/InsertCode', ['title' => 'Insert code', 'settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function audit(): Response
    {
        return Inertia::render('Admin/Audit', ['title' => 'Audit', 'auditLogs' => AuditLog::with('actor')->latest()->paginate(50)]);
    }
}
