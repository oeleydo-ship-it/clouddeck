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
            'maintenance_banner' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['platform_name' => 'string', 'support_email' => 'string', 'registration_enabled' => 'boolean', 'email_verification_required' => 'boolean', 'public_site_enabled' => 'boolean', 'dns_enabled' => 'boolean', 'maintenance_banner' => 'string'] as $key => $type) {
            $value = $type === 'boolean' ? ($request->boolean($key) ? '1' : '0') : ($data[$key] ?? '');
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
}
