<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support Mode "Read Only" blocks destructive and sensitive writes while still allowing
 * inspection of the customer's console.
 */
class EnsureImpersonationWriteAccess
{
    public function __construct(private readonly ImpersonationManager $impersonation) {}

    /** @var list<string> */
    private array $allowedRouteNames = [
        'impersonation.exit',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->isReadOnly($request)) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        if ($request->routeIs(...$this->allowedRouteNames)) {
            return $next($request);
        }

        abort(403, 'Support Mode is read-only. Exit impersonation or switch to Full Access to make changes.');
    }
}
