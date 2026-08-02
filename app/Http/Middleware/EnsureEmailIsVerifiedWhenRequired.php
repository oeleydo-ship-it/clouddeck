<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedWhenRequired
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->emailVerificationRequired()) {
            return $next($request);
        }
        $user = $request->user();
        if (! $user || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
