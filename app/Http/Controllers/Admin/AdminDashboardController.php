<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingRequest;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $users = User::with('currentSubscription.plan')->when($request->query('search'), fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->latest()->paginate(20)->withQueryString();

        return view('admin.dashboard', ['stats' => ['users' => User::count(), 'suspended' => User::whereNotNull('suspended_at')->count(), 'subscriptions' => Subscription::whereIn('status', ['active', 'trialing'])->count(), 'billing_requests' => BillingRequest::where('status', 'pending')->count()], 'users' => $users, 'plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->get(), 'flags' => FeatureFlag::orderBy('key')->get(), 'billingRequests' => BillingRequest::with(['user', 'plan'])->where('status', 'pending')->latest()->get(), 'auditLogs' => AuditLog::with('actor')->latest()->limit(50)->get(), 'settings' => SystemSetting::all()->keyBy('key')]);
    }
}
