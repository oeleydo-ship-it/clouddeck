<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MailSettingsTestMessage;
use App\Models\NotificationChannel;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class AdminSettingController extends Controller
{
    /**
     * Mail keys and whether each is safe for a Blade view to read. Everything here is
     * fine to show back to an administrator except the SMTP password.
     */
    private const MAIL_KEYS = [
        'mail_host' => true,
        'mail_port' => true,
        'mail_encryption' => true,
        'mail_username' => true,
        'mail_password' => false,
        'mail_from_address' => true,
        'mail_from_name' => true,
    ];

    public function update(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => ['nullable', 'string', 'max:60'],
            'support_email' => ['nullable', 'email'],
            'registration_enabled' => ['sometimes', 'boolean'],
            'email_verification_required' => ['sometimes', 'boolean'],
            'public_site_enabled' => ['sometimes', 'boolean'],
            'dns_enabled' => ['sometimes', 'boolean'],
            'staging_sites_enabled' => ['sometimes', 'boolean'],
            'allow_impersonate_admins' => ['sometimes', 'boolean'],
            'maintenance_banner' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ([
            'platform_name' => 'string',
            'support_email' => 'string',
            'registration_enabled' => 'boolean',
            'email_verification_required' => 'boolean',
            'public_site_enabled' => 'boolean',
            'dns_enabled' => 'boolean',
            'staging_sites_enabled' => 'boolean',
            'allow_impersonate_admins' => 'boolean',
            'maintenance_banner' => 'string',
        ] as $key => $type) {
            $value = $type === 'boolean' ? ($request->boolean($key) ? '1' : '0') : ($data[$key] ?? '');
            $settings->put($key, $value, $type, true);
        }

        $audit->record($request, 'settings.updated', null, [], ['keys' => array_keys($data)]);

        return back()->with('status', 'System settings updated.');
    }

    public function managedServers(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'managed_servers_enabled' => ['sometimes', 'boolean'],
            'managed_cloud_provider' => ['required', Rule::in(['digitalocean', 'hetzner'])],
            'managed_cloud_token' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->put('managed_servers_enabled', $request->boolean('managed_servers_enabled') ? '1' : '0', 'boolean', true);
        $settings->put('managed_cloud_provider', (string) $data['managed_cloud_provider'], 'string', true);
        if (filled($data['managed_cloud_token'] ?? null)) {
            $settings->put('managed_cloud_token', (string) $data['managed_cloud_token'], 'string', false);
        }

        $audit->record($request, 'settings.managed_servers_updated', null, [], [
            'enabled' => $request->boolean('managed_servers_enabled'),
            'provider' => $data['managed_cloud_provider'],
            'token_updated' => filled($data['managed_cloud_token'] ?? null),
        ]);

        return back()->with('status', 'Managed server settings saved.');
    }

    /**
     * Customer-facing pricing for each managed server configuration: a default markup
     * percentage over the provider's raw infra cost, plus optional per-size overrides so
     * the 4 GB and 8 GB tiers (etc.) can each be priced independently.
     */
    public function managedServerPricing(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $settings->put('managed_markup_percent', (string) ($data['markup_percent'] ?? 0), 'string', true);

        $prices = collect($data['prices'] ?? [])
            ->filter(fn ($price) => $price !== null && $price !== '' && is_numeric($price) && (float) $price > 0)
            ->map(fn ($price) => round((float) $price, 2))
            ->all();
        $settings->saveManagedSizePrices($prices);

        $audit->record($request, 'settings.managed_server_pricing_updated', null, [], [
            'markup_percent' => $data['markup_percent'] ?? 0,
            'overrides' => count($prices),
        ]);

        return back()->with('status', 'Managed server pricing saved.');
    }

    public function logo(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $request->validate([
            // Served in the header of every page to every visitor, so this stays a narrow
            // list of formats, small enough that it never becomes the slowest asset.
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);

        $previous = $settings->get('logo_path');
        $path = $request->file('logo')->store('branding', 'public');
        $settings->put('logo_path', $path, 'string', true);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $audit->record($request, 'settings.logo_updated', null, ['logo_path' => $previous], ['logo_path' => $path]);

        return back()->with('status', 'Logo updated.');
    }

    public function branding(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $request->validate(['logo_image_only' => ['sometimes', 'boolean']]);

        $enabled = $request->boolean('logo_image_only');
        $settings->put('logo_image_only', $enabled ? '1' : '0', 'boolean', true);
        $audit->record($request, 'settings.branding_updated', null, [], ['logo_image_only' => $enabled]);

        return back()->with('status', 'Logo display updated.');
    }

    public function destroyLogo(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $previous = $settings->get('logo_path');

        if ($previous) {
            Storage::disk('public')->delete($previous);
            $settings->put('logo_path', '', 'string', true);
            $audit->record($request, 'settings.logo_removed', null, ['logo_path' => $previous], []);
        }

        return back()->with('status', 'Logo removed.');
    }

    public function favicon(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $request->validate([
            'favicon' => [
                'required',
                'file',
                'max:512',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ext = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($ext, ['ico', 'png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                        $fail('The favicon must be an ico, png, jpeg, svg, or webp file.');
                    }
                },
            ],
        ]);

        $previous = $settings->get('favicon_path');
        $path = $request->file('favicon')->store('branding', 'public');
        $settings->put('favicon_path', $path, 'string', true);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $audit->record($request, 'settings.favicon_updated', null, ['favicon_path' => $previous], ['favicon_path' => $path]);

        return back()->with('status', 'Favicon updated.');
    }

    public function destroyFavicon(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $previous = $settings->get('favicon_path');

        if ($previous) {
            Storage::disk('public')->delete($previous);
            $settings->put('favicon_path', '', 'string', true);
            $audit->record($request, 'settings.favicon_removed', null, ['favicon_path' => $previous], []);
        }

        return back()->with('status', 'Favicon removed.');
    }

    public function mail(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (self::MAIL_KEYS as $key => $public) {
            // A blank password means "keep the stored one". The form cannot repopulate it,
            // so without this, saving any other mail field would silently wipe the password.
            if ($key === 'mail_password' && blank($data[$key] ?? null)) {
                continue;
            }

            $settings->put($key, (string) ($data[$key] ?? ''), 'string', $public);
        }

        $audit->record($request, 'settings.mail_updated', null, [], ['host' => $data['mail_host'] ?? null]);

        return back()->with('status', 'Mail settings saved. Send a test message to confirm they work.');
    }

    /**
     * Mail configuration that is never exercised is configuration you discover is wrong
     * when a customer cannot reset their password. This proves it before that happens.
     */
    public function testMail(Request $request, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        if (blank($settings->get('mail_host'))) {
            return back()->withErrors(['test_email' => 'Save an SMTP host before sending a test message.']);
        }

        try {
            Mail::to($data['test_email'])->send(new MailSettingsTestMessage($settings->branding()['name']));
        } catch (Throwable $e) {
            return back()->withErrors(['test_email' => 'Sending failed: '.$e->getMessage()]);
        }

        return back()->with('status', 'Test message sent to '.$data['test_email'].'.');
    }

    /**
     * Platform-wide mute for client alert emails. In-app bell delivery is unchanged;
     * password resets, verification, and team invites always send.
     */
    public function notifications(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $eventKeys = array_keys(NotificationChannel::EVENTS);

        $request->validate([
            'client_email_notifications_enabled' => ['sometimes', 'boolean'],
            'client_email_billing_payment_failed' => ['sometimes', 'boolean'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', Rule::in($eventKeys)],
        ]);

        $settings->put(
            'client_email_notifications_enabled',
            $request->boolean('client_email_notifications_enabled') ? '1' : '0',
            'boolean',
            true,
        );

        $enabledEvents = $request->input('events', []);
        if (! is_array($enabledEvents)) {
            $enabledEvents = [];
        }

        foreach ($eventKeys as $event) {
            $settings->put(
                'client_email_event_'.$event,
                in_array($event, $enabledEvents, true) ? '1' : '0',
                'boolean',
                true,
            );
        }

        $settings->put(
            'client_email_billing_payment_failed',
            $request->boolean('client_email_billing_payment_failed') ? '1' : '0',
            'boolean',
            true,
        );

        $audit->record($request, 'settings.notifications_updated', null, [], [
            'operational_enabled' => $request->boolean('client_email_notifications_enabled'),
            'events' => $enabledEvents,
            'billing_failed' => $request->boolean('client_email_billing_payment_failed'),
        ]);

        return back()->with('status', 'Notification center saved. Disabled emails stay in the in-app bell only.');
    }

    public function landing(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'landing_hero_eyebrow' => ['nullable', 'string', 'max:80'],
            'landing_hero_headline' => ['nullable', 'string', 'max:160'],
            'landing_hero_subcopy' => ['nullable', 'string', 'max:600'],
            'landing_hero_cta_primary' => ['nullable', 'string', 'max:60'],
            'landing_hero_cta_secondary' => ['nullable', 'string', 'max:60'],
            'landing_hero_microcopy' => ['nullable', 'string', 'max:120'],
            'landing_steps_eyebrow' => ['nullable', 'string', 'max:80'],
            'landing_steps_headline' => ['nullable', 'string', 'max:160'],
            'landing_steps_subcopy' => ['nullable', 'string', 'max:400'],
            'landing_step_1_title' => ['nullable', 'string', 'max:120'],
            'landing_step_1_body' => ['nullable', 'string', 'max:400'],
            'landing_step_2_title' => ['nullable', 'string', 'max:120'],
            'landing_step_2_body' => ['nullable', 'string', 'max:400'],
            'landing_step_3_title' => ['nullable', 'string', 'max:120'],
            'landing_step_3_body' => ['nullable', 'string', 'max:400'],
            'landing_cta_headline' => ['nullable', 'string', 'max:160'],
            'landing_cta_subcopy' => ['nullable', 'string', 'max:400'],
            'landing_cta_button' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ($data as $key => $value) {
            $settings->put($key, (string) ($value ?? ''), 'string', true);
        }

        $audit->record($request, 'settings.landing_updated', null, [], ['keys' => array_keys($data)]);

        return back()->with('status', 'Landing page copy saved.');
    }

    public function seo(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $pageKeys = [];
        foreach (array_keys($settings->marketingSeoPages()) as $page) {
            if ($page === 'home') {
                $pageKeys["seo_home_title"] = ['nullable', 'string', 'max:180'];
                $pageKeys["seo_home_description"] = ['nullable', 'string', 'max:320'];
                $pageKeys["seo_home_og_image"] = ['nullable', 'url', 'max:500'];

                continue;
            }

            $pageKeys["seo_page_{$page}_title"] = ['nullable', 'string', 'max:180'];
            $pageKeys["seo_page_{$page}_description"] = ['nullable', 'string', 'max:320'];
            $pageKeys["seo_page_{$page}_og_image"] = ['nullable', 'url', 'max:500'];
        }

        $data = $request->validate([
            'seo_default_title' => ['nullable', 'string', 'max:180'],
            'seo_title_template' => ['nullable', 'string', 'max:180'],
            'seo_default_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_og_image' => ['nullable', 'url', 'max:500'],
            'seo_robots' => ['nullable', 'string', 'max:80'],
            'seo_robots_txt' => ['nullable', 'string', 'max:10000'],
            ...$pageKeys,
        ]);

        foreach ($data as $key => $value) {
            $settings->put($key, (string) ($value ?? ''), 'string', true);
        }

        $audit->record($request, 'settings.seo_updated', null, [], ['keys' => array_keys($data)]);

        return back()->with('status', 'SEO settings saved.');
    }

    public function analytics(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'ga_measurement_id' => ['nullable', 'string', 'max:40', 'regex:/^(G-|UA-)?[A-Za-z0-9-]*$/'],
        ]);

        $settings->put('ga_measurement_id', (string) ($data['ga_measurement_id'] ?? ''), 'string', true);
        $audit->record($request, 'settings.analytics_updated', null, [], ['ga_measurement_id' => $data['ga_measurement_id'] ?? null]);

        return back()->with('status', 'Analytics settings saved.');
    }

    public function webmaster(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'gsc_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_-]*$/'],
        ]);

        $settings->put('gsc_verification', (string) ($data['gsc_verification'] ?? ''), 'string', true);
        $audit->record($request, 'settings.webmaster_updated', null, [], []);

        return back()->with('status', 'Webmaster verification saved.');
    }

    public function ai(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        if ($request->input('ai_base_url') === '') {
            $request->merge(['ai_base_url' => null]);
        }

        $data = $request->validate([
            'ai_guide_enabled' => ['sometimes', 'boolean'],
            'ai_blog_enabled' => ['sometimes', 'boolean'],
            'ai_provider' => ['required', Rule::in(SystemSettings::aiProviders())],
            'openai_api_key' => ['nullable', 'string', 'max:255'],
            'openai_model' => ['nullable', 'string', 'max:80'],
            'ai_base_url' => ['nullable', 'string', 'max:255', 'url'],
            'ai_guide_system_prompt' => ['nullable', 'string', 'max:4000'],
            'ai_blog_avoid_phrases' => ['nullable', 'string', 'max:4000'],
            'ai_blog_insert_words' => ['nullable', 'string', 'max:2000'],
            'ai_blog_style_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $provider = (string) $data['ai_provider'];
        $settings->put('ai_provider', $provider, 'string', true);
        $settings->put('ai_guide_enabled', $request->boolean('ai_guide_enabled') ? '1' : '0', 'boolean', true);
        $settings->put('ai_blog_enabled', $request->boolean('ai_blog_enabled') ? '1' : '0', 'boolean', true);

        if (filled($data['openai_api_key'] ?? null)) {
            $settings->put('openai_api_key', (string) $data['openai_api_key'], 'string', false);
        }

        $model = filled($data['openai_model'] ?? null)
            ? (string) $data['openai_model']
            : $settings->defaultAiModel($provider);
        $settings->put('openai_model', $model, 'string', true);
        $settings->put('ai_base_url', (string) ($data['ai_base_url'] ?? ''), 'string', true);
        $settings->put('ai_guide_system_prompt', (string) ($data['ai_guide_system_prompt'] ?? ''), 'string', false);
        $settings->put('ai_blog_avoid_phrases', (string) ($data['ai_blog_avoid_phrases'] ?? ''), 'string', true);
        $settings->put('ai_blog_insert_words', (string) ($data['ai_blog_insert_words'] ?? ''), 'string', true);
        $settings->put('ai_blog_style_notes', (string) ($data['ai_blog_style_notes'] ?? ''), 'string', true);

        $audit->record($request, 'settings.ai_updated', null, [], [
            'enabled' => $request->boolean('ai_guide_enabled'),
            'blog_enabled' => $request->boolean('ai_blog_enabled'),
            'provider' => $provider,
            'model' => $model,
            'base_url' => $data['ai_base_url'] ?? null,
            'key_updated' => filled($data['openai_api_key'] ?? null),
        ]);

        return back()->with('status', 'AI settings saved.');
    }

    public function googleAuth(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'google_auth_enabled' => ['sometimes', 'boolean'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->put('google_auth_enabled', $request->boolean('google_auth_enabled') ? '1' : '0', 'boolean', true);

        if (filled($data['google_client_id'] ?? null)) {
            $settings->put('google_client_id', (string) $data['google_client_id'], 'string', false);
        }

        // Blank secret means keep the stored value — same pattern as Stripe / OpenAI.
        if (filled($data['google_client_secret'] ?? null)) {
            $settings->put('google_client_secret', (string) $data['google_client_secret'], 'string', false);
        }

        if ($id = $settings->get('google_client_id')) {
            config(['services.google.client_id' => $id]);
        }
        if ($secret = $settings->get('google_client_secret')) {
            config(['services.google.client_secret' => $secret]);
        }
        config([
            'services.google.enabled' => $request->boolean('google_auth_enabled'),
            'services.google.redirect' => rtrim((string) config('app.url'), '/').'/auth/google/callback',
        ]);

        $audit->record($request, 'settings.google_auth_updated', null, [], [
            'enabled' => $request->boolean('google_auth_enabled'),
            'client_id_updated' => filled($data['google_client_id'] ?? null),
            'client_secret_updated' => filled($data['google_client_secret'] ?? null),
        ]);

        return back()->with('status', 'Google Auth settings saved.');
    }

    public function stripe(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'stripe_key' => ['nullable', 'string', 'max:255', 'starts_with:pk_'],
            'stripe_secret' => ['nullable', 'string', 'max:255', 'starts_with:sk_,rk_'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'starts_with:whsec_'],
        ]);

        // Blank secret fields mean "keep the stored value" — same pattern as mail/AI keys.
        if (filled($data['stripe_key'] ?? null)) {
            $settings->put('stripe_key', (string) $data['stripe_key'], 'string', false);
        }

        if (filled($data['stripe_secret'] ?? null)) {
            $settings->put('stripe_secret', (string) $data['stripe_secret'], 'string', false);
        }

        if (filled($data['stripe_webhook_secret'] ?? null)) {
            $settings->put('stripe_webhook_secret', (string) $data['stripe_webhook_secret'], 'string', false);
        }

        // Apply immediately so this request's follow-up status page sees the new config.
        foreach ([
            'stripe_key' => 'key',
            'stripe_secret' => 'secret',
            'stripe_webhook_secret' => 'webhook_secret',
        ] as $settingKey => $configKey) {
            if ($value = $settings->get($settingKey)) {
                config(["services.stripe.{$configKey}" => $value]);
            }
        }

        $audit->record($request, 'settings.stripe_updated', null, [], [
            'key_updated' => filled($data['stripe_key'] ?? null),
            'secret_updated' => filled($data['stripe_secret'] ?? null),
            'webhook_secret_updated' => filled($data['stripe_webhook_secret'] ?? null),
        ]);

        return back()->with('status', 'Stripe credentials saved.');
    }

    public function osBackupPricing(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'os_backup_gb_price' => ['required', 'numeric', 'min:0.5', 'max:1000'],
        ]);

        $cents = (int) round(((float) $data['os_backup_gb_price']) * 100);
        $settings->put('os_backup_gb_price_cents', (string) max(50, $cents), 'string', true);
        $audit->record($request, 'settings.os-backup-pricing-updated', null, [], [
            'os_backup_gb_price_cents' => max(50, $cents),
        ]);

        return back()->with('status', 'OS backup storage pricing saved.');
    }

    public function insertCode(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'insert_code_head' => ['nullable', 'string', 'max:50000'],
            'insert_code_body' => ['nullable', 'string', 'max:50000'],
            'insert_code_on_marketing' => ['sometimes', 'boolean'],
            'insert_code_on_console' => ['sometimes', 'boolean'],
        ]);

        // Intentionally stored and later rendered as raw HTML/JS for trusted super-admins only.
        $settings->put('insert_code_head', (string) ($data['insert_code_head'] ?? ''), 'string', false);
        $settings->put('insert_code_body', (string) ($data['insert_code_body'] ?? ''), 'string', false);
        $settings->put('insert_code_on_marketing', $request->boolean('insert_code_on_marketing') ? '1' : '0', 'boolean', true);
        $settings->put('insert_code_on_console', $request->boolean('insert_code_on_console') ? '1' : '0', 'boolean', true);

        $audit->record($request, 'settings.insert_code_updated', null, [], [
            'on_marketing' => $request->boolean('insert_code_on_marketing'),
            'on_console' => $request->boolean('insert_code_on_console'),
            'head_bytes' => strlen((string) ($data['insert_code_head'] ?? '')),
            'body_bytes' => strlen((string) ($data['insert_code_body'] ?? '')),
        ]);

        return back()->with('status', 'Insert code saved.');
    }

    public function objectStorage(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'object_storage_provider' => ['required', Rule::in(['digitalocean', 'hetzner', 'wasabi', 'custom'])],
            'object_storage_key' => ['nullable', 'string', 'max:255'],
            'object_storage_secret' => ['nullable', 'string', 'max:255'],
            'object_storage_region' => ['nullable', 'string', 'max:64'],
            'object_storage_bucket' => ['nullable', 'string', 'max:255'],
            'object_storage_endpoint' => ['nullable', 'string', 'max:255'],
            'object_storage_url' => ['nullable', 'string', 'max:255'],
            'object_storage_path_style' => ['sometimes', 'boolean'],
            'database_backup_disk' => ['required', Rule::in(['local', 's3'])],
        ]);

        $settings->put('object_storage_provider', $data['object_storage_provider'], 'string', true);

        if (filled($data['object_storage_key'] ?? null)) {
            $settings->put('object_storage_key', (string) $data['object_storage_key'], 'string', false);
        }
        if (filled($data['object_storage_secret'] ?? null)) {
            $settings->put('object_storage_secret', (string) $data['object_storage_secret'], 'string', false);
        }

        $settings->put('object_storage_region', (string) ($data['object_storage_region'] ?? ''), 'string', true);
        $settings->put('object_storage_bucket', (string) ($data['object_storage_bucket'] ?? ''), 'string', true);
        $settings->put('object_storage_endpoint', (string) ($data['object_storage_endpoint'] ?? ''), 'string', true);
        $settings->put('object_storage_url', (string) ($data['object_storage_url'] ?? ''), 'string', true);
        $settings->put('object_storage_path_style', $request->boolean('object_storage_path_style') ? '1' : '0', 'boolean', true);
        $settings->put('database_backup_disk', $data['database_backup_disk'], 'string', true);

        if ($data['database_backup_disk'] === 's3' && ! $settings->objectStorageConfigured()) {
            return back()->withInput()->withErrors([
                'database_backup_disk' => 'Configure access key, secret, region, and bucket before selecting object storage as the default disk.',
            ]);
        }

        $this->applyObjectStorageConfig($settings);

        $audit->record($request, 'settings.object_storage_updated', null, [], [
            'provider' => $data['object_storage_provider'],
            'bucket' => $data['object_storage_bucket'] ?? null,
            'region' => $data['object_storage_region'] ?? null,
            'endpoint' => $data['object_storage_endpoint'] ?? null,
            'path_style' => $request->boolean('object_storage_path_style'),
            'database_backup_disk' => $data['database_backup_disk'],
            'key_updated' => filled($data['object_storage_key'] ?? null),
            'secret_updated' => filled($data['object_storage_secret'] ?? null),
        ]);

        return back()->with('status', 'Object storage settings saved.');
    }

    public function testObjectStorage(Request $request, SystemSettings $settings): RedirectResponse
    {
        $this->applyObjectStorageConfig($settings);

        if (! $settings->objectStorageConfigured()) {
            return back()->with('error', 'Save access key, secret, region, and bucket before testing.');
        }

        $path = 'uplary-storage-tests/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.txt';

        try {
            Storage::disk('s3')->put($path, 'uplary object storage probe');
            $exists = Storage::disk('s3')->exists($path);
            Storage::disk('s3')->delete($path);

            if (! $exists) {
                return back()->with('error', 'Upload succeeded but the object could not be read back. Check bucket permissions.');
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Object storage test failed: '.$e->getMessage());
        }

        return back()->with('status', 'Object storage connection succeeded.');
    }

    private function applyObjectStorageConfig(SystemSettings $settings): void
    {
        $storage = $settings->objectStorage();

        if (filled($storage['key'])) {
            config(['filesystems.disks.s3.key' => $storage['key']]);
        }
        if (filled($storage['secret'])) {
            config(['filesystems.disks.s3.secret' => $storage['secret']]);
        }
        if (filled($storage['region'])) {
            config(['filesystems.disks.s3.region' => $storage['region']]);
        }
        if (filled($storage['bucket'])) {
            config(['filesystems.disks.s3.bucket' => $storage['bucket']]);
        }
        if (filled($storage['endpoint'])) {
            config(['filesystems.disks.s3.endpoint' => $storage['endpoint']]);
        }
        if (filled($storage['url'])) {
            config(['filesystems.disks.s3.url' => $storage['url']]);
        }

        config(['filesystems.disks.s3.use_path_style_endpoint' => $storage['path_style']]);
        config(['remote_management.database_backup_disk' => $settings->databaseBackupDisk()]);
    }
}
