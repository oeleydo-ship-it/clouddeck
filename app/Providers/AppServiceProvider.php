<?php

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\ManualBillingGateway;
use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Listeners\SendDeploymentNotification;
use App\Models\AlertIncident;
use App\Models\Deployment;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        $this->applyStripeCredentialsFromSettings();
        $this->applyMailCredentialsFromSettings();

        // Resolved per render rather than shared once, so a logo or name saved on the
        // settings page shows up on the next request instead of after a deploy.
        // Branding is shared with every view (not only the layout): child pages that
        // @extends layouts.app render before the layout, so a layout-only composer
        // leaves $branding undefined in their content.
        View::composer('*', function ($view): void {
            $view->with('branding', app(SystemSettings::class)->branding());
        });

        View::composer('layouts.app', function ($view): void {
            $settings = app(SystemSettings::class);
            $view->with('dnsEnabled', $settings->dnsEnabled());
            $view->with('publicSiteEnabled', $settings->publicSiteEnabled());
            $view->with('shellAlerts', $this->shellAlerts());
            $view->with('seo', $settings->seo());
            $view->with('analytics', $settings->analytics());
            $view->with('aiGuideEnabled', $settings->aiGuideEnabled());
            $view->with('insertCode', $settings->insertCode());
        });
    }

    /**
     * What the header bell shows. Real open incidents and real failed deployments only —
     * an empty bell is the honest answer when nothing is wrong, and each entry links
     * straight to the page where the problem can be acted on.
     *
     * @return array<int, array{title: string, description: string, href: string, tone: string}>
     */
    private function shellAlerts(): array
    {
        $user = auth()->user();

        if (! $user || ! Schema::hasTable('alert_incidents')) {
            return [];
        }

        try {
            $incidents = AlertIncident::query()
                ->with('server')
                ->where('status', 'open')
                ->whereHas('server', fn ($query) => $query->accessibleTo($user))
                ->latest('started_at')
                ->limit(5)
                ->get()
                ->map(fn (AlertIncident $incident) => [
                    'title' => Str::headline($incident->metric).' alert',
                    'description' => ($incident->server?->name ?? 'Server').' · '.$incident->started_at?->diffForHumans(),
                    'href' => $incident->server ? route('servers.manage', $incident->server) : route('servers.index'),
                    'tone' => $incident->severity === 'critical' ? 'danger' : 'warning',
                ]);

            $deployments = Deployment::query()
                ->with('site')
                ->where('status', DeploymentStatus::Failed)
                ->whereHas('site.server', fn ($query) => $query->accessibleTo($user))
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Deployment $deployment) => [
                    'title' => 'Deployment failed',
                    'description' => ($deployment->site?->domain ?? 'Site').' · '.$deployment->created_at?->diffForHumans(),
                    'href' => route('deployments.show', $deployment),
                    'tone' => 'danger',
                ]);

            return $incidents->concat($deployments)->take(6)->values()->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
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
     * Stripe credentials from Admin → Payments (or the installer) live in system_settings,
     * encrypted at rest. Overriding config here means every config('services.stripe.*')
     * caller picks them up; .env still wins when no setting has been saved.
     */
    private function applyStripeCredentialsFromSettings(): void
    {
        try {
            $settings = app(SystemSettings::class);

            foreach ([
                'stripe_key' => 'key',
                'stripe_secret' => 'secret',
                'stripe_webhook_secret' => 'webhook_secret',
            ] as $settingKey => $configKey) {
                $value = $settings->get($settingKey);
                if (filled($value)) {
                    config(["services.stripe.{$configKey}" => $value]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Could not load Stripe credentials from settings: '.$e->getMessage());
        }
    }
}
