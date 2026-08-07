<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterAuthentication;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    use RedirectsAfterAuthentication;

    public function create(Request $request): View|RedirectResponse
    {
        return $request->session()->has('login.id') ? view('auth.two-factor-challenge') : redirect('/login');
    }

    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:30']]);
        $user = User::find($request->session()->get('login.id'));

        if (! $user || $user->suspended_at || ! $user->two_factor_confirmed_at) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect('/login');
        }

        $valid = $twoFactor->verify($user->two_factor_secret, $request->string('code'))
            || $twoFactor->consumeRecoveryCode($user, $request->string('code'));

        if (! $valid) {
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        Auth::login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }
}
