<?php

namespace App\Services;

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
