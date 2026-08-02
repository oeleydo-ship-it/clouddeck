<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Installation;
use App\Services\PlatformDefaults;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InstallController extends Controller
{
    /**
     * Settings the wizard writes, and how each is stored. Stripe credentials are the reason
     * is_public matters: everything else here is safe to read from a Blade view, but the
     * secret and webhook secret must never leave the server.
     */
    private const SETTINGS = [
        'support_email' => ['type' => 'string', 'public' => true],
        'registration_enabled' => ['type' => 'boolean', 'public' => true],
        'email_verification_required' => ['type' => 'boolean', 'public' => true],
        'stripe_secret' => ['type' => 'string', 'public' => false],
        'stripe_webhook_secret' => ['type' => 'string', 'public' => false],
    ];

    public function show(Installation $installation): View|RedirectResponse
    {
        if ($installation->isInstalled()) {
            return redirect()->route('dashboard');
        }

        return view('install');
    }

    public function store(Request $request, Installation $installation, PlatformDefaults $defaults, SystemSettings $settings): RedirectResponse
    {
        // Re-checked here and not only in show(): without it, two people opening the wizard
        // at once could both submit and the second would silently take over the instance.
        abort_if($installation->isInstalled(), 403, 'CloudDeck is already installed.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'support_email' => ['nullable', 'email', 'max:255'],
            'registration_enabled' => ['sometimes', 'boolean'],
            'email_verification_required' => ['sometimes', 'boolean'],
            'stripe_secret' => ['nullable', 'string', 'max:255', 'starts_with:sk_,rk_'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'starts_with:whsec_'],
        ]);

        $user = DB::transaction(function () use ($request, $data, $defaults) {
            $defaults->ensure();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]);
            $user->subscriptions()->create(['plan_id' => $defaults->freePlan()->id, 'provider' => 'system', 'status' => 'active']);

            foreach (self::SETTINGS as $key => $meta) {
                $value = $meta['type'] === 'boolean'
                    ? ($request->boolean($key) ? '1' : '0')
                    : (string) ($data[$key] ?? '');
                SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $meta['type'], 'is_public' => $meta['public']]);
            }
            SystemSetting::updateOrCreate(['key' => 'installed_at'], ['value' => now()->toIso8601String(), 'type' => 'string', 'is_public' => false]);

            return $user;
        });

        foreach ([...array_keys(self::SETTINGS), 'installed_at'] as $key) {
            $settings->forget($key);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('status', 'CloudDeck is installed. You are signed in as the administrator.');
    }
}
