<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsAfterAuthentication
{
    /**
     * Prefer the intended URL from the session, but only when it is a same-app
     * relative path — never an absolute or protocol-relative off-site URL.
     */
    protected function redirectAfterLogin(Request $request, string $default = '/dashboard'): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended', $default);

        if (! is_string($intended)
            || ! str_starts_with($intended, '/')
            || str_starts_with($intended, '//')
            || str_contains($intended, '://')) {
            return redirect($default);
        }

        return redirect($intended);
    }
}
