<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep impersonation state honest: drop stale sessions, and never let a support session
 * reach /admin (the customer identity must not inherit the admin console).
 */
class EnsureImpersonationIntegrity
{
    public function __construct(private readonly ImpersonationManager $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->isImpersonating($request)) {
            return $next($request);
        }

        $admin = $this->impersonation->impersonator($request);
        $record = $this->impersonation->activeSession($request);

        if (! $admin || $admin->suspended_at || ! $record || ! $record->isActive()) {
            $this->impersonation->abandon($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your support access session ended. Sign in again.',
            ]);
        }

        if ($request->user()?->id !== $record->target_user_id) {
            $this->impersonation->abandon($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your support access session was invalid.',
            ]);
        }

        // Exit and logout are the only mutating escapes while impersonating into admin space.
        if ($request->is('admin', 'admin/*') && ! $request->routeIs('impersonation.exit')) {
            abort(403, 'Leave impersonation before opening the admin console.');
        }

        return $next($request);
    }
}
