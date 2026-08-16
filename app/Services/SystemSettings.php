<?php

namespace App\Services;

use App\Support\DatabaseBootstrap;
use App\Models\NotificationChannel;
use App\Models\Post;
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
        if (DatabaseBootstrap::shouldDeferDatabaseAccess()) {
            return $default;
        }

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
     * Cloudflare API token that may edit DNS on the platform staging apex zone.
     */
    public function platformDnsCloudflareToken(): ?string
    {
        return $this->get('platform_dns_cloudflare_token');
    }

    public function platformDnsCloudflareZoneId(): ?string
    {
        return $this->get('platform_dns_cloudflare_zone_id');
    }

    /**
     * Token + zone id present — platform staging A records can be published automatically.
     */
    public function platformStagingDnsReady(): bool
    {
        return filled($this->platformDnsCloudflareToken()) && filled($this->platformDnsCloudflareZoneId());
    }

    /**
     * Platform-provided VMs (not BYOS). Off until a superadmin enables the feature and
     * saves a cloud API token that Uplary uses to create VPS for customers.
     */
    public function managedServersEnabled(): bool
    {
        return $this->boolean('managed_servers_enabled', false);
    }

    public function managedCloudProvider(): string
    {
        $provider = strtolower((string) ($this->get('managed_cloud_provider', 'digitalocean') ?: 'digitalocean'));

        return in_array($provider, ['digitalocean', 'hetzner'], true) ? $provider : 'digitalocean';
    }

    public function managedCloudToken(): ?string
    {
        return $this->get('managed_cloud_token');
    }

    /**
     * Toggle on and a non-empty platform token — ready for customer managed provision.
     */
    public function managedServersReady(): bool
    {
        return $this->managedServersEnabled() && filled($this->managedCloudToken());
    }

    /**
     * Monthly price per GB of OS backup add-on capacity, in cents (default $0.50).
     */
    public function osBackupGbPriceCents(): int
    {
        return max(50, (int) $this->get('os_backup_gb_price_cents', '50'));
    }

    /**
     * Default percentage markup applied over the provider's raw infra cost when a size has
     * no explicit override in managedSizePrices(). Lets an admin price every configuration
     * (1 GB, 4 GB, 8 GB, …) above cost without pricing each one by hand.
     */
    public function managedMarkupPercent(): float
    {
        $value = $this->get('managed_markup_percent', '0');

        return max(0.0, (float) $value);
    }

    /**
     * Per-size customer price overrides, keyed by provider size slug (e.g. `s-1vcpu-4gb`).
     * A size present here is billed at this exact monthly price regardless of the markup
     * percentage — the 4 GB and 8 GB tiers rarely carry the same margin.
     *
     * @return array<string, float>
     */
    public function managedSizePrices(): array
    {
        $raw = $this->get('managed_size_prices', '[]') ?: '[]';
        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? collect($decoded)
                ->filter(fn ($price) => is_numeric($price) && (float) $price > 0)
                ->map(fn ($price) => round((float) $price, 2))
                ->all()
            : [];
    }

    public function saveManagedSizePrices(array $prices): void
    {
        $this->put('managed_size_prices', json_encode($prices), 'json', false);
    }

    /**
     * The price a customer is billed for a given provider size: an explicit override when
     * set, otherwise the platform's markup percentage applied over the raw infra cost.
     *
     * @param  array{slug?: string, price_monthly?: float|int}  $size
     */
    public function managedServerPrice(array $size): float
    {
        $slug = (string) ($size['slug'] ?? '');
        $infra = (float) ($size['price_monthly'] ?? 0);
        $overrides = $this->managedSizePrices();

        if ($slug !== '' && isset($overrides[$slug]) && (float) $overrides[$slug] > 0) {
            return round((float) $overrides[$slug], 2);
        }

        return round($infra * (1 + $this->managedMarkupPercent() / 100), 2);
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
        $favicon = $this->get('favicon_path');
        $faviconUrl = $favicon && Storage::disk('public')->exists($favicon) ? Storage::disk('public')->url($favicon) : null;

        return [
            'name' => $this->get('platform_name', config('app.name', 'Uplary')),
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
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
     *     steps: list<array{title: string, body: string}>,
     *     cta_headline: string,
     *     cta_subcopy: string,
     *     cta_button: string
     * }
     */
    public function landing(): array
    {
        $name = $this->branding()['name'];

        $managed = $this->managedServersEnabled();

        return [
            'hero_eyebrow' => $this->get('landing_hero_eyebrow') ?: $name,
            'hero_headline' => $this->get('landing_hero_headline') ?: 'Provision servers. Deploy sites. Stay in control.',
            'hero_subcopy' => $this->get('landing_hero_subcopy') ?: ($managed
                ? "{$name} can provision a managed server for you, or connect a VPS you already pay for — deploy Laravel, WordPress, and React, and run day-to-day ops from one panel."
                : "{$name} is the SaaS panel for connecting a VPS you already pay for — deploy Laravel, WordPress, and React, and run day-to-day ops. Your cloud bill stays with your provider."),
            'hero_cta_primary' => $this->get('landing_hero_cta_primary') ?: 'Create free account',
            'hero_cta_secondary' => $this->get('landing_hero_cta_secondary') ?: 'See how it works',
            'hero_microcopy' => $this->get('landing_hero_microcopy') ?: ($managed
                ? 'Managed servers or bring your own · no lock-in'
                : 'Works with your VPS · no server lock-in'),
            'steps_eyebrow' => $this->get('landing_steps_eyebrow') ?: 'Getting started',
            'steps_headline' => $this->get('landing_steps_headline') ?: 'Three simple steps.',
            'steps_subcopy' => $this->get('landing_steps_subcopy') ?: ($managed
                ? "Pick a managed server or connect your own VPS. {$name} installs the stack so you do not write server scripts by hand."
                : "You do not need to write server scripts by hand. {$name} sets up the common pieces for you."),
            'steps' => [
                [
                    'title' => $this->get('landing_step_1_title') ?: ($managed ? 'Provision or connect a server' : 'Connect a server'),
                    'body' => $this->get('landing_step_1_body') ?: ($managed
                        ? "Launch a platform-hosted size, or attach a VPS over SSH. {$name} installs nginx, PHP, and the worker stack."
                        : "Attach a DigitalOcean, Hetzner, or other VPS over SSH. {$name} installs nginx, PHP, and the worker stack."),
                ],
                [
                    'title' => $this->get('landing_step_2_title') ?: 'Create a Laravel, WordPress, or React site',
                    'body' => $this->get('landing_step_2_body') ?: 'Point a domain, pick the stack, and the console writes the vhost, release root, and environment for you.',
                ],
                [
                    'title' => $this->get('landing_step_3_title') ?: 'Deploy, SSL, and monitor',
                    'body' => $this->get('landing_step_3_body') ?: 'Push from git, issue Let’s Encrypt, and watch uptime — with rollback if a release fails.',
                ],
            ],
            'cta_headline' => $this->get('landing_cta_headline') ?: 'Ready to try it?',
            'cta_subcopy' => $this->get('landing_cta_subcopy') ?: ($managed
                ? "Make an account, provision a managed server or connect your own, and deploy your next site with {$name}."
                : "Make an account, connect a server, and deploy your next site with {$name}."),
            'cta_button' => $this->get('landing_cta_button') ?: 'Create free account',
        ];
    }

    /**
     * Marketing pages that accept per-page SEO overrides in Admin → SEO.
     *
     * @return array<string, array{label: string, route: string}>
     */
    public function marketingSeoPages(): array
    {
        return [
            'home' => ['label' => 'Homepage', 'route' => 'home'],
            'about' => ['label' => 'About', 'route' => 'about'],
            'features' => ['label' => 'Features', 'route' => 'features'],
            'use_cases' => ['label' => 'Use cases', 'route' => 'use-cases'],
            'blog' => ['label' => 'Blog', 'route' => 'blog'],
            'contact' => ['label' => 'Contact', 'route' => 'contact'],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     title_template: string,
     *     description: string,
     *     keywords: ?string,
     *     og_image: ?string,
     *     robots: string,
     *     robots_txt: string
     * }
     */
    public function seo(): array
    {
        $name = $this->branding()['name'];

        return [
            'title' => $this->get('seo_default_title') ?: $name,
            'title_template' => $this->get('seo_title_template') ?: '{page} | {site}',
            'description' => $this->get('seo_default_description') ?: "{$name} helps you provision servers, deploy Laravel and WordPress, and run day-to-day ops on infrastructure you own.",
            'keywords' => $this->get('seo_keywords'),
            'og_image' => $this->get('seo_og_image'),
            'robots' => $this->get('seo_robots') ?: 'index,follow',
            'robots_txt' => $this->robotsTxt(),
        ];
    }

    /**
     * Compose a document title from the admin template (`{page}` / `{site}`).
     */
    public function applyTitleTemplate(string $page, ?string $site = null): string
    {
        $site ??= $this->branding()['name'];
        $template = $this->get('seo_title_template') ?: '{page} | {site}';

        return str_replace(['{page}', '{site}'], [$page, $site], $template);
    }

    /**
     * Resolve the most specific title / description / OG image for a marketing page key.
     *
     * @return array{title: string, description: string, og_image: ?string}
     */
    public function pageSeo(string $page): array
    {
        $pages = $this->marketingSeoPages();
        $label = $pages[$page]['label'] ?? Str::headline(str_replace('_', ' ', $page));
        $defaults = $this->seo();
        $site = $this->branding()['name'];

        if ($page === 'home') {
            return [
                'title' => $this->get('seo_home_title')
                    ?: $this->get('seo_default_title')
                    ?: $site,
                'description' => $this->get('seo_home_description') ?: $defaults['description'],
                'og_image' => $this->get('seo_home_og_image') ?: $defaults['og_image'],
            ];
        }

        $titleOverride = $this->get("seo_page_{$page}_title");
        $descriptionOverride = $this->get("seo_page_{$page}_description");
        $ogOverride = $this->get("seo_page_{$page}_og_image");

        return [
            'title' => $titleOverride ?: $this->applyTitleTemplate($label, $site),
            'description' => $descriptionOverride ?: $defaults['description'],
            'og_image' => $ogOverride ?: $defaults['og_image'],
        ];
    }

    /**
     * Resolve SEO tags for a published blog post (per-post fields, then excerpt / title).
     *
     * @return array{title: string, description: string, og_image: ?string}
     */
    public function postSeo(Post $post): array
    {
        $defaults = $this->seo();
        $site = $this->branding()['name'];

        return [
            'title' => filled($post->meta_title)
                ? (string) $post->meta_title
                : $this->applyTitleTemplate((string) $post->title, $site),
            'description' => filled($post->meta_description)
                ? (string) $post->meta_description
                : (filled($post->excerpt) ? (string) $post->excerpt : $defaults['description']),
            'og_image' => $post->cover_url ?: $defaults['og_image'],
        ];
    }

    /**
     * Body of /robots.txt. Empty admin value falls back to allow-all plus the sitemap URL.
     */
    public function robotsTxt(): string
    {
        $custom = $this->get('seo_robots_txt');
        if (filled($custom)) {
            return rtrim(str_replace("\r\n", "\n", $custom))."\n";
        }

        return "User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n";
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

    public const AI_PROVIDER_OPENAI = 'openai';

    public const AI_PROVIDER_MOONSHOT = 'moonshot';

    /**
     * @return list<string>
     */
    public static function aiProviders(): array
    {
        return [self::AI_PROVIDER_OPENAI, self::AI_PROVIDER_MOONSHOT];
    }

    public function aiGuideEnabled(): bool
    {
        return $this->boolean('ai_guide_enabled', false) && filled($this->aiApiKey());
    }

    /**
     * Superadmin blog auto-drafts. Shares the AI key/model with the guide; toggled separately
     * so the customer-facing chat can stay off while admins still generate posts.
     */
    public function aiBlogEnabled(): bool
    {
        return $this->boolean('ai_blog_enabled', false) && filled($this->aiApiKey());
    }

    /**
     * Phrases the blog generator must avoid (AI clichés). One phrase per line in settings;
     * empty setting falls back to built-in defaults.
     *
     * @return list<string>
     */
    public function aiBlogAvoidPhrases(): array
    {
        $raw = (string) ($this->get('ai_blog_avoid_phrases') ?? '');
        $lines = $this->linesFromSetting($raw);
        if ($lines !== []) {
            return $lines;
        }

        return self::defaultAiBlogAvoidPhrases();
    }

    /**
     * Optional words/phrases the draft should weave in naturally (training hints).
     *
     * @return list<string>
     */
    public function aiBlogInsertWords(): array
    {
        return $this->linesFromSetting((string) ($this->get('ai_blog_insert_words') ?? ''));
    }

    /**
     * Free-form voice notes for blog drafts (tone, audience, house style).
     */
    public function aiBlogStyleNotes(): ?string
    {
        $notes = $this->get('ai_blog_style_notes');

        return filled($notes) ? (string) $notes : null;
    }

    /**
     * @return list<string>
     */
    public static function defaultAiBlogAvoidPhrases(): array
    {
        return [
            'digital world',
            "In today's fast-paced digital landscape",
            "In today's digital age",
            "In today's fast-paced world",
            'delve into',
            'dive into',
            'unlock the power',
            "it's important to note",
            'it is important to note',
            'in conclusion',
            'game-changer',
            'game changer',
            'cutting-edge',
            'cutting edge',
            'leverage synergies',
            'ever-evolving',
            'at the end of the day',
            'when it comes to',
            'nestled',
            'tapestry',
            'realm of',
            'landscape of',
        ];
    }

    /**
     * @return list<string>
     */
    private function linesFromSetting(string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique(fn (string $line) => mb_strtolower($line))
            ->values()
            ->all();
    }

    /**
     * OpenAI-compatible provider used by the guide and blog draft generators.
     */
    public function aiProvider(): string
    {
        $provider = $this->get('ai_provider', self::AI_PROVIDER_OPENAI) ?: self::AI_PROVIDER_OPENAI;

        return in_array($provider, self::aiProviders(), true) ? $provider : self::AI_PROVIDER_OPENAI;
    }

    /**
     * Encrypted API key (stored under openai_api_key for backwards compatibility).
     */
    public function aiApiKey(): ?string
    {
        return $this->get('openai_api_key');
    }

    public function aiModel(): string
    {
        $stored = $this->get('openai_model');

        return filled($stored) ? $stored : $this->defaultAiModel($this->aiProvider());
    }

    /**
     * Chat Completions base URL without a trailing slash. Optional ai_base_url overrides the
     * provider default (e.g. https://api.moonshot.cn/v1 for China region).
     */
    public function aiBaseUrl(): string
    {
        $custom = $this->get('ai_base_url');
        if (filled($custom)) {
            return rtrim((string) $custom, '/');
        }

        return $this->defaultAiBaseUrl($this->aiProvider());
    }

    public function aiChatCompletionsUrl(): string
    {
        return $this->aiBaseUrl().'/chat/completions';
    }

    public function defaultAiModel(string $provider): string
    {
        return match ($provider) {
            self::AI_PROVIDER_MOONSHOT => 'kimi-k3',
            default => 'gpt-4o-mini',
        };
    }

    public function defaultAiBaseUrl(string $provider): string
    {
        return match ($provider) {
            self::AI_PROVIDER_MOONSHOT => 'https://api.moonshot.ai/v1',
            default => 'https://api.openai.com/v1',
        };
    }

    /** @deprecated Prefer aiApiKey() — kept for callers that still use the OpenAI-named helpers. */
    public function openaiApiKey(): ?string
    {
        return $this->aiApiKey();
    }

    /** @deprecated Prefer aiModel(). */
    public function openaiModel(): string
    {
        return $this->aiModel();
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

    /**
     * S3-compatible object storage for off-server backups (Spaces, Hetzner, Wasabi, etc.).
     * Settings override AWS_* from .env when set.
     *
     * @return array{
     *     provider: string,
     *     key: ?string,
     *     secret: ?string,
     *     region: ?string,
     *     bucket: ?string,
     *     endpoint: ?string,
     *     url: ?string,
     *     path_style: bool,
     *     configured: bool
     * }
     */
    public function objectStorage(): array
    {
        $key = $this->get('object_storage_key') ?: config('filesystems.disks.s3.key');
        $secret = $this->get('object_storage_secret') ?: config('filesystems.disks.s3.secret');
        $bucket = $this->get('object_storage_bucket') ?: config('filesystems.disks.s3.bucket');
        $region = $this->get('object_storage_region') ?: config('filesystems.disks.s3.region');
        $endpoint = $this->get('object_storage_endpoint') ?: config('filesystems.disks.s3.endpoint');
        $url = $this->get('object_storage_url') ?: config('filesystems.disks.s3.url');
        $pathStyleStored = $this->get('object_storage_path_style');
        $pathStyle = $pathStyleStored !== null
            ? in_array($pathStyleStored, ['1', 'true'], true)
            : (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);

        return [
            'provider' => $this->get('object_storage_provider', 'custom') ?: 'custom',
            'key' => $key,
            'secret' => $secret,
            'region' => $region,
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'url' => $url,
            'path_style' => $pathStyle,
            'configured' => filled($key) && filled($secret) && filled($bucket) && filled($region),
        ];
    }

    public function objectStorageConfigured(): bool
    {
        return $this->objectStorage()['configured'];
    }

    /**
     * Master switch for operational client alert emails (uptime, deploys, SSL, backups, …).
     * In-app bell notifications are unaffected. Defaults on so existing installs keep mailing.
     */
    public function clientEmailNotificationsEnabled(): bool
    {
        return $this->boolean('client_email_notifications_enabled', true);
    }

    /**
     * Whether a specific operational event may leave the SMTP path. Requires the master
     * switch; a missing per-event setting means allowed.
     */
    public function clientEmailEventAllowed(string $event): bool
    {
        if (! $this->clientEmailNotificationsEnabled()) {
            return false;
        }

        return $this->boolean('client_email_event_'.$event, true);
    }

    /**
     * Stripe payment-failed mail is separate from operational alerts so operators can mute
     * noisy ops emails without silencing billing recovery.
     */
    public function clientEmailBillingFailedAllowed(): bool
    {
        return $this->boolean('client_email_billing_payment_failed', true);
    }

    /**
     * @return array<string, bool>
     */
    public function clientEmailEventToggles(): array
    {
        $toggles = [];

        foreach (array_keys(NotificationChannel::EVENTS) as $event) {
            $toggles[$event] = $this->boolean('client_email_event_'.$event, true);
        }

        return $toggles;
    }

    /**
     * Default private disk for new database/site backups. Admin setting overrides env.
     */
    public function databaseBackupDisk(): string
    {
        $disk = $this->get('database_backup_disk') ?: config('remote_management.database_backup_disk', 'local');

        return in_array($disk, ['local', 's3'], true) ? $disk : 'local';
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
