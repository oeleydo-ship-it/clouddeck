<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
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

        return redirect()->intended('/dashboard');
    }

    public function registerForm(): View
    {
        abort_if(SystemSetting::where('key', 'registration_enabled')->first()?->value === '0', 403, 'Registration is temporarily disabled.');

        return view('auth.register');
    }

    public function register(Request $request, SystemSettings $settings): RedirectResponse
    {
        abort_if(SystemSetting::where('key', 'registration_enabled')->first()?->value === '0', 403, 'Registration is temporarily disabled.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'unique:users'], 'password' => ['required', 'confirmed', 'min:12']]);
        $verificationRequired = $settings->emailVerificationRequired();
        $user = User::create([...$data, 'password' => Hash::make($data['password']), 'email_verified_at' => $verificationRequired ? null : now()]);
        if ($plan = Plan::where('slug', 'free')->where('active', true)->first()) {
            $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'system']);
        }
        if ($verificationRequired) {
            event(new Registered($user));
        }
        Auth::login($user);

        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
