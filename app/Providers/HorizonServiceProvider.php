<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate and auth callback.
     *
     * Defining only Gate::define is not enough — Horizon::check() ignores the gate unless
     * Horizon::auth() is wired (parent::boot → authorization() does that). Without it,
     * production always returns 403 even for super admins.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if ($user === null) {
                return false;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            $allowed = collect(config('horizon.allowed_emails', []))
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->filter()
                ->all();

            if ($allowed === []) {
                return false;
            }

            return in_array(strtolower(trim((string) $user->email)), $allowed, true);
        });
    }
}
