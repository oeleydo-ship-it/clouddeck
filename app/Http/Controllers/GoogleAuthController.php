<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterAuthentication;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    use RedirectsAfterAuthentication;

    public function redirect(SystemSettings $settings): RedirectResponse
    {
        abort_unless($settings->googleAuthEnabled(), 503, 'Google Sign-In is not configured.');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SystemSettings $settings): RedirectResponse
    {
        abort_unless($settings->googleAuthEnabled(), 503, 'Google Sign-In is not configured.');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in failed. Please try again.',
            ]);
        }

        if (! $this->googleEmailVerified($googleUser)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your Google email must be verified before you can sign in.',
            ]);
        }

        $email = Str::lower((string) $googleUser->getEmail());
        $googleId = (string) $googleUser->getId();

        if ($email === '' || $googleId === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Google did not return a usable account.',
            ]);
        }

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user) {
            if ($user->suspended_at) {
                return redirect()->route('login')->withErrors([
                    'email' => 'This account is suspended. Contact support.',
                ]);
            }

            // Link Google to an existing password account; never change role.
            if ($user->google_id !== $googleId) {
                $user->forceFill(['google_id' => $googleId])->save();
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } else {
            abort_if(
                SystemSetting::where('key', 'registration_enabled')->first()?->value === '0',
                403,
                'Registration is temporarily disabled.',
            );

            $user = DB::transaction(function () use ($googleUser, $email, $googleId) {
                $user = User::query()->create([
                    'name' => Str::limit((string) ($googleUser->getName() ?: strstr($email, '@', true) ?: 'User'), 100, ''),
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
                // Role stays at the DB default (customer); never assign admin via OAuth.
                $user->forceFill(['role' => 'customer'])->save();

                if ($plan = Plan::where('slug', 'free')->where('active', true)->first()) {
                    $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'system']);
                }

                return $user;
            });
        }

        if ($user->two_factor_confirmed_at) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);

            return redirect('/two-factor-challenge');
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }

    private function googleEmailVerified(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false;

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}
