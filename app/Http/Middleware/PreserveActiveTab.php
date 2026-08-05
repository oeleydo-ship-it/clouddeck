<?php

namespace App\Http\Middleware;

use App\Support\ActiveTab;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreserveActiveTab
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $tab = ActiveTab::sanitize($request->input('_tab'));
        if ($tab === null) {
            return $response;
        }

        $response->setTargetUrl(ActiveTab::append($response->getTargetUrl(), $tab));

        return $response;
    }
}
