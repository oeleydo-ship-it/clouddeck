<?php

namespace App\Http\Controllers;

use App\Models\UserImpersonationSession;
use App\Services\ImpersonationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationExitController extends Controller
{
    public function __invoke(Request $request, ImpersonationManager $impersonation): RedirectResponse
    {
        abort_unless($impersonation->isImpersonating($request), 403);

        $returnFallback = route('admin.users');
        $targetId = $request->user()?->id;

        $admin = $impersonation->stop($request, UserImpersonationSession::STATUS_ENDED);

        if (! $admin) {
            return redirect()->route('login')->withErrors([
                'email' => 'Support access ended. Sign in with your administrator account.',
            ]);
        }

        $returnTo = $request->session()->pull('impersonation_return_to')
            ?: ($targetId ? route('admin.users.show', $targetId) : $returnFallback);

        return redirect()->to($returnTo)->with('status', 'Impersonation ended. You are back in your administrator account.');
    }
}
