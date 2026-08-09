<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\FeatureManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPlanController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = Plan::create($data);
        $audit->record($request, 'plan.created', $plan, [], $data);

        return back()->with('status', 'Plan created.');
    }

    public function update(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $plan);
        $old = $plan->only(['name', 'slug', 'monthly_price', 'yearly_price', 'limits', 'features', 'active', 'public']);
        $plan->update($data);
        $audit->record($request, 'plan.updated', $plan, $old, $data);

        return back()->with('status', 'Plan updated.');
    }

    public function stripe(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'stripe_monthly_price_id' => ['nullable', 'regex:/^price_[A-Za-z0-9]+$/', 'max:100'],
            'stripe_yearly_price_id' => ['nullable', 'regex:/^price_[A-Za-z0-9]+$/', 'max:100', 'different:stripe_monthly_price_id'],
        ]);
        $old = $plan->only(['stripe_monthly_price_id', 'stripe_yearly_price_id']);
        $plan->update([
            'stripe_monthly_price_id' => $data['stripe_monthly_price_id'] ?: null,
            'stripe_yearly_price_id' => $data['stripe_yearly_price_id'] ?: null,
        ]);
        $audit->record($request, 'plan.stripe-prices-updated', $plan, $old, $data);

        return back()->with('status', 'Stripe price mapping updated.');
    }

    public function destroy(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        // Removing a plan people are paying on would leave those subscriptions pointing at
        // nothing, and every quota check reads the plan. Move them first.
        if ($plan->subscriptions()->whereIn('status', ['active', 'trialing', 'past_due'])->exists()) {
            return back()->withErrors(['plan' => 'This plan still has subscribers. Move them to another plan before deleting it.']);
        }

        $audit->record($request, 'plan.deleted', $plan, $plan->only(['name', 'slug', 'monthly_price', 'yearly_price']), []);
        $plan->delete();

        return back()->with('status', 'Plan deleted.');
    }

    /**
     * Prices are entered the way a person writes them — 29 or 29.99 — and stored in the
     * minor units Stripe and the rest of the billing code expect. The old form asked for
     * raw cents, which is a quiet way to charge someone a hundred times too much.
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $featureRules = collect(FeatureManager::keys())
            ->mapWithKeys(fn (string $key) => ['feature_'.$key => ['sometimes', 'boolean']])
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('plans')->ignore($plan)],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'size:3'],
            'servers' => ['required', 'integer', 'min:-1'],
            'managed_servers' => ['required', 'integer', 'min:-1'],
            'sites' => ['required', 'integer', 'min:-1'],
            'managed_sites' => ['required', 'integer', 'min:-1'],
            'databases' => ['required', 'integer', 'min:-1'],
            'api_tokens' => ['required', 'integer', 'min:-1'],
            'teams' => ['required', 'integer', 'min:-1'],
            'team_members' => ['required', 'integer', 'min:-1'],
            'os_backup_gb' => ['required', 'integer', 'min:-1'],
            'active' => ['sometimes', 'boolean'],
            'public' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            ...$featureRules,
        ]);

        $features = collect(FeatureManager::keys())
            ->mapWithKeys(fn (string $key) => [$key => $request->boolean('feature_'.$key)])
            ->all();

        $limits = collect(['servers', 'managed_servers', 'sites', 'managed_sites', 'databases', 'api_tokens', 'teams', 'team_members', 'os_backup_gb'])
            ->mapWithKeys(fn ($key) => [$key => (int) $data[$key]])
            ->all();

        // Non-zero hosting quotas without the matching access gate show as "Access off"
        // while Billing still advertises a count. Align access with those quotas so Free
        // (and every plan) can actually use the capacity you set.
        if ($limits['servers'] !== 0) {
            $features['providers'] = true;
        }
        if ($limits['managed_servers'] !== 0 || $limits['managed_sites'] !== 0) {
            $features['managed_servers'] = true;
        }
        if ($limits['os_backup_gb'] !== 0) {
            $features['os_backups'] = true;
        }

        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'monthly_price' => (int) round($data['monthly_price'] * 100),
            'yearly_price' => (int) round($data['yearly_price'] * 100),
            'currency' => strtoupper($data['currency']),
            'limits' => $limits,
            'features' => $features,
            'active' => $request->boolean('active'),
            'public' => $request->boolean('public'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
