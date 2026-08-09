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
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Each administrative section is its own page rather than a tab in one document. The tabbed
 * version loaded every customer, plan, flag, billing request, and audit row on every visit,
 * and a refresh always dropped the operator back on the first tab.
 */
class AdminDashboardController extends Controller
{
    public function overview(): View
    {
        return view('admin.overview', [
            'stats' => [
                'users' => User::count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(),
                'billing_requests' => BillingRequest::where('status', 'pending')->count(),
            ],
            'auditLogs' => AuditLog::with('actor')->latest()->limit(10)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        return view('admin.users', [
            'users' => User::with('currentSubscription.plan')
                ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function plans(): View
    {
        return view('admin.plans', ['plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->get()]);
    }

    public function features(): View
    {
        return view('admin.features', ['flags' => FeatureFlag::orderBy('key')->get()]);
    }

    public function billing(): View
    {
        return view('admin.billing', ['billingRequests' => BillingRequest::with(['user', 'plan'])->where('status', 'pending')->latest()->get()]);
    }

    public function payments(): View
    {
        return view('admin.payments', [
            'plans' => Plan::orderBy('sort_order')->get(),
            'settings' => SystemSetting::all()->keyBy('key'),
        ]);
    }

    public function storage(): View
    {
        return view('admin.storage', [
            'settings' => SystemSetting::all()->keyBy('key'),
            'objectStorage' => app(SystemSettings::class)->objectStorage(),
            'databaseBackupDisk' => app(SystemSettings::class)->databaseBackupDisk(),
        ]);
    }

    public function settings(): View
    {
        return view('admin.settings', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function mail(): View
    {
        return view('admin.mail', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function notifications(): View
    {
        $system = app(SystemSettings::class);

        return view('admin.notifications', [
            'settings' => SystemSetting::all()->keyBy('key'),
            'clientEmailEnabled' => $system->clientEmailNotificationsEnabled(),
            'eventToggles' => $system->clientEmailEventToggles(),
            'billingFailedAllowed' => $system->clientEmailBillingFailedAllowed(),
        ]);
    }

    public function managedServers(CloudProviderManager $providers, SystemSettings $systemSettings): View
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

        return view('admin.managed-servers', [
            'settings' => SystemSetting::all()->keyBy('key'),
            'ready' => $ready,
            'managedSizes' => $managedSizes,
            'managedMarkupPercent' => $systemSettings->managedMarkupPercent(),
            'managedSizePrices' => $systemSettings->managedSizePrices(),
        ]);
    }

    public function pages(): View
    {
        return view('admin.pages', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function seo(): View
    {
        return view('admin.seo', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function analytics(): View
    {
        return view('admin.analytics', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function webmaster(): View
    {
        return view('admin.webmaster', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function ai(): View
    {
        return view('admin.ai', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function googleAuth(): View
    {
        return view('admin.google-auth', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function insertCode(): View
    {
        return view('admin.insert-code', ['settings' => SystemSetting::all()->keyBy('key')]);
    }

    public function audit(): View
    {
        return view('admin.audit', ['auditLogs' => AuditLog::with('actor')->latest()->paginate(50)]);
    }
}
