<?php

namespace App\Http\Middleware;

use App\Services\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->allows($request, $feature)) {
            return $next($request);
        }

        return $this->deny($request, $feature);
    }

    public function allows(Request $request, string $feature): bool
    {
        $user = $request->user();

        return (bool) ($user && app(FeatureManager::class)->enabled($feature, $user));
    }

    public function deny(Request $request, string $feature): Response
    {
        $label = FeatureManager::catalog()[$feature] ?? str_replace('_', ' ', $feature);

        // APIs and non-GET mutations stay hard-denied. Console page loads show an upgrade
        // prompt instead of the bare "This feature is not enabled" 403.
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, 'This feature is not enabled for your account.');
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return redirect()
                ->route('billing.index')
                ->with('error', "{$label} is not included in your plan. Subscribe or upgrade to unlock it.");
        }

        return Inertia::render('Entitlements/Upgrade', [
            'title' => $label.' isn’t on your plan',
            'feature' => $feature,
            'label' => $label,
            'billingHref' => route('billing.index'),
        ])->toResponse($request);
    }
}
