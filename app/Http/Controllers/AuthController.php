<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterAuthentication;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    use RedirectsAfterAuthentication;

    public function loginForm(SystemSettings $settings): Response
    {
        return Inertia::render('Auth/Login', [
            'googleAuthEnabled' => $settings->googleAuthEnabled(),
            'googleButtonLabel' => $settings->googleAuthEnabled() ? 'Continue with Google' : null,
            'googleRedirect' => $settings->googleAuthEnabled() ? route('auth.google.redirect') : null,
            'passwordRequestHref' => route('password.request'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::validate($credentials)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();
        if ($user->suspended_at) {
            return back()->withErrors(['email' => 'This account is suspended. Contact support.'])->onlyInput('email');
        }
        if ($user->two_factor_confirmed_at) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->id, 'login.remember' => $request->boolean('remember')]);

            return redirect('/two-factor-challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }

    public function registerForm(SystemSettings $settings): Response
    {
        abort_if(SystemSetting::where('key', 'registration_enabled')->first()?->value === '0', 403, 'Registration is temporarily disabled.');

        return Inertia::render('Auth/Register', [
            'googleAuthEnabled' => $settings->googleAuthEnabled(),
            'googleButtonLabel' => $settings->googleAuthEnabled() ? 'Continue with Google' : null,
            'googleRedirect' => $settings->googleAuthEnabled() ? route('auth.google.redirect') : null,
        ]);
    }

    public function register(Request $request, SystemSettings $settings): RedirectResponse
    {
        abort_if(SystemSetting::where('key', 'registration_enabled')->first()?->value === '0', 403, 'Registration is temporarily disabled.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        $verificationRequired = $settings->emailVerificationRequired();
        // Explicit attributes only — never accept role / elevated fields from the request.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => $verificationRequired ? null : now(),
        ]);
        $user->forceFill(['role' => 'customer'])->save();
        if ($plan = Plan::where('slug', 'free')->where('active', true)->first()) {
            $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'system']);
        }
        if ($verificationRequired) {
            event(new Registered($user));
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        app(\App\Services\ImpersonationManager::class)->terminateOnLogout($request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
