<?php

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\ManualBillingGateway;
use App\Events\DeploymentFinished;
use App\Listeners\SendDeploymentNotification;
use App\Models\SystemSetting;
use App\Notifications\Channels\OutboundChannel;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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

        // One channel that fans out to whatever destinations a customer has configured, so a
        // notification never has to know that Discord or Telegram exist.
        Notification::resolved(function ($service) {
            $service->extend('clouddeck', fn ($app) => $app->make(OutboundChannel::class));
        });
        Gate::define('viewHorizon', fn ($user) => $user->isSuperAdmin());
        $this->applyStripeCredentialsFromSettings();
        $this->applyMailCredentialsFromSettings();

        // Resolved per render rather than shared once, so a logo or name saved on the
        // settings page shows up on the next request instead of after a deploy.
        View::composer('layouts.app', function ($view): void {
            $view->with('branding', app(SystemSettings::class)->branding());
        });
    }

    /**
     * SMTP credentials saved on the settings page, applied over config so every mailable,
     * notification, and password reset picks them up. .env still wins when nothing has been
     * saved, which keeps local development and any existing deployment working untouched.
     *
     * SMTP rather than a provider SDK: Resend, Postmark, SES and the rest all speak it, so
     * one form covers whichever service the operator is on today and whichever they move to.
     */
    private function applyMailCredentialsFromSettings(): void
    {
        $settings = app(SystemSettings::class);
        $host = $settings->get('mail_host');

        if (blank($host)) {
            return;
        }

        $encryption = $settings->get('mail_encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $settings->get('mail_port', '587'),
            'mail.mailers.smtp.username' => $settings->get('mail_username'),
            'mail.mailers.smtp.password' => $settings->get('mail_password'),
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
        ]);

        if ($from = $settings->get('mail_from_address')) {
            config(['mail.from.address' => $from, 'mail.from.name' => $settings->get('mail_from_name', $settings->branding()['name'])]);
        }
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
