<?php

namespace App\Http\Middleware;

use App\Services\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($request->user() && app(FeatureManager::class)->enabled($feature, $request->user()), 403, 'This feature is not enabled for your account.');

        return $next($request);
    }
}
