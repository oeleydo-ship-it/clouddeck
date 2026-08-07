<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class SystemSettings
{
    public function get(string $key, ?string $default = null): ?string
    {
        // Reached from a view composer and from the provider that configures mail, so this
        // also runs during `migrate` against an empty database. A missing table is not an
        // error there, it just means nothing has been configured yet.
        try {
            if (! Schema::hasTable('system_settings')) {
                return $default;
            }
        } catch (Throwable) {
            return $default;
        }

        $value = Cache::remember("system-setting:{$key}", 60, fn () => SystemSetting::whereKey($key)->first()?->value);

        return $value === null || $value === '' ? $default : $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : in_array($value, ['1', 'true'], true);
    }

    public function put(string $key, ?string $value, string $type = 'string', bool $public = true): void
    {
        SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'type' => $type, 'is_public' => $public]);
        $this->forget($key);
    }

    public function emailVerificationRequired(): bool
    {
        // Local console work should not be blocked by verification notices or mail delivery.
        if (app()->environment('local')) {
            return false;
        }

        return $this->boolean('email_verification_required', config('clouddeck.email_verification_required', true));
    }

    /**
     * Whether the marketing pages are served at all. An instance running on a subdomain is
     * often only ever the application, and there a home page is a wrong front door rather
     * than a feature.
     */
    public function publicSiteEnabled(): bool
    {
        return $this->boolean('public_site_enabled', true);
    }

    /**
     * Whether the DNS section is offered at all. Not every install manages DNS from here —
     * plenty of teams keep it with whoever holds the registrar — and an empty section
     * inviting a Cloudflare token is a question those operators would rather not be asked.
     */
    public function dnsEnabled(): bool
    {
        return $this->boolean('dns_enabled', true);
    }

    /**
     * Whether customers may create staging sites linked to production. Off until a
     * superadmin enables it and configures the platform staging domain.
     */
    public function stagingSitesEnabled(): bool
    {
        return $this->boolean('staging_sites_enabled', false);
    }

    /**
     * Apex used for platform-hosted staging hostnames: {slug}.staging.{domain}.
     */
    public function stagingPlatformDomain(): string
    {
        return Str::lower($this->get('staging_platform_domain', 'uplary.com') ?: 'uplary.com');
    }

    /**
     * Where someone who is not signed in should land. With the marketing pages turned off
     * the site starts at the sign-in form.
     */
    public function landingUrl(): string
    {
        return $this->publicSiteEnabled() ? route('home') : route('login');
    }

    /**
     * What the header renders. Falls back to the built-in name so an instance that has
     * never opened the settings page still looks like a product rather than a blank bar.
     */
    public function branding(): array
    {
        $logo = $this->get('logo_path');
        $logoUrl = $logo && Storage::disk('public')->exists($logo) ? Storage::disk('public')->url($logo) : null;

        return [
            'name' => $this->get('platform_name', config('app.name', 'Uplary')),
            'logo_url' => $logoUrl,
            // Never suppress the text fallback unless there is an image that can replace it.
            'logo_image_only' => $logoUrl !== null && $this->boolean('logo_image_only'),
        ];
    }

    /**
     * Editable homepage copy. Empty settings fall back to the built-in landing text so
     * a fresh install still looks finished before an admin opens Settings.
     *
     * @return array{
     *     hero_eyebrow: string,
     *     hero_headline: string,
     *     hero_subcopy: string,
     *     hero_cta_primary: string,
     *     hero_cta_secondary: string,
     *     hero_microcopy: string,
     *     steps_eyebrow: string,
     *     steps_headline: string,
     *     steps_subcopy: string,
     *     cta_headline: string,
     *     cta_subcopy: string,
     *     cta_button: string
     * }
     */
    public function landing(): array
    {
        $name = $this->branding()['name'];

        return [
            'hero_eyebrow' => $this->get('landing_hero_eyebrow') ?: $name,
            'hero_headline' => $this->get('landing_hero_headline') ?: 'Provision servers. Deploy sites. Stay in control.',
            'hero_subcopy' => $this->get('landing_hero_subcopy') ?: "{$name} is the SaaS panel for auto-provisioning your VPS, deploying Laravel and WordPress, and running day-to-day ops — your cloud bill stays with your provider.",
            'hero_cta_primary' => $this->get('landing_hero_cta_primary') ?: 'Create free account',
            'hero_cta_secondary' => $this->get('landing_hero_cta_secondary') ?: 'See how it works',
            'hero_microcopy' => $this->get('landing_hero_microcopy') ?: 'Works with your VPS · no server lock-in',
            'steps_eyebrow' => $this->get('landing_steps_eyebrow') ?: 'Getting started',
            'steps_headline' => $this->get('landing_steps_headline') ?: 'Three simple steps.',
            'steps_subcopy' => $this->get('landing_steps_subcopy') ?: "You do not need to write server scripts by hand. {$name} sets up the common pieces for you.",
            'cta_headline' => $this->get('landing_cta_headline') ?: 'Ready to try it?',
            'cta_subcopy' => $this->get('landing_cta_subcopy') ?: "Make an account, connect a server, and deploy your next site with {$name}.",
            'cta_button' => $this->get('landing_cta_button') ?: 'Create free account',
        ];
    }

    /**
     * @return array{description: string, keywords: ?string, og_image: ?string, robots: string}
     */
    public function seo(): array
    {
        $name = $this->branding()['name'];

        return [
            'description' => $this->get('seo_default_description') ?: "{$name} helps you provision servers, deploy Laravel and WordPress, and run day-to-day ops on infrastructure you own.",
            'keywords' => $this->get('seo_keywords'),
            'og_image' => $this->get('seo_og_image'),
            'robots' => $this->get('seo_robots') ?: 'index,follow',
        ];
    }

    /**
     * @return array{ga_measurement_id: ?string, gsc_verification: ?string}
     */
    public function analytics(): array
    {
        return [
            'ga_measurement_id' => $this->get('ga_measurement_id'),
            'gsc_verification' => $this->get('gsc_verification'),
        ];
    }

    public function aiGuideEnabled(): bool
    {
        return $this->boolean('ai_guide_enabled', false) && filled($this->openaiApiKey());
    }

    public function openaiApiKey(): ?string
    {
        return $this->get('openai_api_key');
    }

    public function openaiModel(): string
    {
        return $this->get('openai_model') ?: 'gpt-4o-mini';
    }

    public function aiGuideSystemPrompt(): string
    {
        $name = $this->branding()['name'];

        return $this->get('ai_guide_system_prompt') ?: <<<PROMPT
You are the in-app guide for {$name}, a SaaS control plane for provisioning VPS servers and deploying Laravel and WordPress sites. Help signed-in users with clear, step-by-step answers about providers, SSH keys, provisioning, sites, deployments, SSL, databases, workers, monitoring, backups, staging, DNS, teams, and billing. Prefer linking them to console areas by name (Dashboard, Servers, Sites, Providers, Documentation). Do not invent billing charges or claim you can change server state yourself. If unsure, say so and suggest Documentation or Contact support. Keep answers concise.
PROMPT;
    }

    /**
     * Publishable Stripe key when saved in admin (or installer). Prefer this over env when set.
     */
    public function stripeKey(): ?string
    {
        return $this->get('stripe_key');
    }

    public function stripeSecret(): ?string
    {
        return $this->get('stripe_secret');
    }

    public function stripeWebhookSecret(): ?string
    {
        return $this->get('stripe_webhook_secret');
    }

    public function googleClientId(): ?string
    {
        return $this->get('google_client_id') ?: config('services.google.client_id');
    }

    public function googleClientSecret(): ?string
    {
        return $this->get('google_client_secret') ?: config('services.google.client_secret');
    }

    /**
     * Whether Google Sign-In should be offered on login/register. Requires credentials
     * (admin settings or .env). An explicit admin toggle wins; otherwise GOOGLE_AUTH_ENABLED
     * defaults to true so env-only installs are not silently invisible.
     */
    public function googleAuthEnabled(): bool
    {
        if (! filled($this->googleClientId()) || ! filled($this->googleClientSecret())) {
            return false;
        }

        $stored = $this->get('google_auth_enabled');
        if ($stored !== null) {
            return in_array($stored, ['1', 'true'], true);
        }

        return filter_var(config('services.google.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Custom HTML/JS snippets for chat widgets and similar third-party embeds.
     * Values are admin-controlled raw markup — never expose to non-admins for editing.
     *
     * @return array{head: ?string, body: ?string, on_marketing: bool, on_console: bool}
     */
    public function insertCode(): array
    {
        return [
            'head' => $this->get('insert_code_head'),
            'body' => $this->get('insert_code_body'),
            'on_marketing' => $this->boolean('insert_code_on_marketing', true),
            'on_console' => $this->boolean('insert_code_on_console', false),
        ];
    }

    public function forget(string $key): void
    {
        Cache::forget("system-setting:{$key}");
    }
}
