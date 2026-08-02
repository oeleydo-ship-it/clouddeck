<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the marketing pages. An instance deployed on a subdomain is usually only the
 * application, and there the home page is a wrong front door: visitors should arrive at
 * the sign-in form instead of a product pitch.
 */
class EnsurePublicSiteEnabled
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->publicSiteEnabled()) {
            return $next($request);
        }

        // Someone already signed in asking for the home page wants the product, not the
        // login form they would immediately be bounced off.
        return redirect()->to($request->user() ? '/dashboard' : route('login'));
    }
}
