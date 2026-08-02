<?php

namespace App\Http\Controllers;

use App\Services\QuotaManager;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request, TwoFactorService $twoFactor): View
    {
        return view('account.show', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'sessions' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(),
            'provisioningUri' => $request->user()->two_factor_secret && ! $request->user()->two_factor_confirmed_at
                ? $twoFactor->provisioningUri($request->user(), $request->user()->two_factor_secret) : null,
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', Rule::unique('users')->ignore($request->user())], 'timezone' => ['required', 'timezone']]);
        $emailChanged = $data['email'] !== $request->user()->email;
        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }
        $request->user()->forceFill($data)->save();
        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:12']]);
        $request->user()->update(['password' => $data['password']]);
        Auth::logoutOtherDevices($data['current_password']);

        return back()->with('status', 'Password updated.');
    }

    public function enableTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => $twoFactor->generateSecret(), 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('status', 'Scan the setup URI and confirm a code.');
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        abort_unless($request->user()->two_factor_secret, 422);
        if (! $twoFactor->verify($request->user()->two_factor_secret, $request->string('code'))) {
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }
        $plain = $twoFactor->recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($plain), 'two_factor_confirmed_at' => now()])->save();

        return back()->with('status', 'Two-factor authentication enabled.')->with('recovery_codes', $plain);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('status', 'Two-factor authentication disabled.');
    }

    public function token(Request $request, QuotaManager $quotas): RedirectResponse
    {
        $quotas->assertCanCreate($request->user(), 'api_tokens');
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $token = $request->user()->createToken($data['name'], ['servers:read', 'servers:write'], now()->addDays(90));

        return back()->with('status', 'API token created.')->with('plain_token', $token->plainTextToken);
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('status', 'API token revoked.');
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('status', 'Session revoked.');
    }
}
