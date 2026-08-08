<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards managed-server provision routes. UI hiding alone would leave kept links working.
 */
class EnsureManagedServersEnabled
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->managedServersReady(), 404);

        return $next($request);
    }
}
