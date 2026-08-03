<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the DNS section. Hiding the nav entry alone would leave every DNS URL reachable
 * by anyone who kept a link or guessed one, so the switch is enforced on the routes and
 * the sidebar simply reflects it.
 */
class EnsureDnsEnabled
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->dnsEnabled(), 404);

        return $next($request);
    }
}
