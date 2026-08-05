<?php

namespace App\Http\Middleware;

use App\Services\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(private readonly Installation $installation) {}

    /**
     * A Uplary instance with no administrator is unusable, so send every browser request
     * to the wizard until one exists. Webhooks are exempt: they are machine callers that
     * would follow a redirect into an HTML page and record a delivery as successful.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('webhooks/*') || $request->is('up')) {
            return $next($request);
        }

        if (! $this->installation->isInstalled()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Uplary is not installed yet.'], 503)
                : redirect()->route('install');
        }

        return $next($request);
    }
}
