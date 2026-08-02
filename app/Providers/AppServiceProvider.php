<?php

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\ManualBillingGateway;
use App\Events\DeploymentFinished;
use App\Listeners\SendDeploymentNotification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingGateway::class, ManualBillingGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(DeploymentFinished::class, SendDeploymentNotification::class);
        Gate::define('viewHorizon', fn ($user) => $user->isSuperAdmin());
        $this->applyStripeCredentialsFromSettings();
    }

    /**
     * Stripe credentials entered in the installer live in system_settings, encrypted at rest,
     * so an operator can set up billing without editing .env on the server. Overriding the
     * config here means every existing config('services.stripe.*') caller picks them up
     * unchanged, with .env still winning when no setting has been saved.
     */
    private function applyStripeCredentialsFromSettings(): void
    {
        // Runs on every boot, including `migrate` against an empty database and any command
        // executed before the tables exist, so a missing table must never be fatal.
        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }

            SystemSetting::whereIn('key', ['stripe_secret', 'stripe_webhook_secret'])
                ->get()
                ->each(function (SystemSetting $setting): void {
                    if (filled($setting->value)) {
                        config(['services.stripe.'.Str::after($setting->key, 'stripe_') => $setting->value]);
                    }
                });
        } catch (Throwable $e) {
            Log::warning('Could not load Stripe credentials from settings: '.$e->getMessage());
        }
    }
}
