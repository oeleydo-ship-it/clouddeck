<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards staging create/promote routes. Hiding UI alone would leave kept links working.
 */
class EnsureStagingSitesEnabled
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->stagingSitesEnabled(), 404);

        return $next($request);
    }
}
