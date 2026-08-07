<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MailSettingsTestMessage;
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
            'staging_platform_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
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
            'staging_platform_domain' => 'string',
            'maintenance_banner' => 'string',
        ] as $key => $type) {
            if ($key === 'staging_platform_domain') {
                $value = strtolower((string) ($data[$key] ?? $settings->stagingPlatformDomain()));
            } else {
                $value = $type === 'boolean' ? ($request->boolean($key) ? '1' : '0') : ($data[$key] ?? '');
            }
            $settings->put($key, $value, $type, true);
        }

        $audit->record($request, 'settings.updated', null, [], ['keys' => array_keys($data)]);

        return back()->with('status', 'System settings updated.');
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
        $data = $request->validate([
            'seo_default_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_og_image' => ['nullable', 'url', 'max:500'],
            'seo_robots' => ['nullable', 'string', 'max:80'],
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
        $data = $request->validate([
            'ai_guide_enabled' => ['sometimes', 'boolean'],
            'openai_api_key' => ['nullable', 'string', 'max:255'],
            'openai_model' => ['nullable', 'string', 'max:80'],
            'ai_guide_system_prompt' => ['nullable', 'string', 'max:4000'],
        ]);

        $settings->put('ai_guide_enabled', $request->boolean('ai_guide_enabled') ? '1' : '0', 'boolean', true);

        if (filled($data['openai_api_key'] ?? null)) {
            $settings->put('openai_api_key', (string) $data['openai_api_key'], 'string', false);
        }

        $settings->put('openai_model', (string) ($data['openai_model'] ?? 'gpt-4o-mini'), 'string', true);
        $settings->put('ai_guide_system_prompt', (string) ($data['ai_guide_system_prompt'] ?? ''), 'string', false);

        $audit->record($request, 'settings.ai_updated', null, [], [
            'enabled' => $request->boolean('ai_guide_enabled'),
            'model' => $data['openai_model'] ?? null,
            'key_updated' => filled($data['openai_api_key'] ?? null),
        ]);

        return back()->with('status', 'AI guide settings saved.');
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
}
