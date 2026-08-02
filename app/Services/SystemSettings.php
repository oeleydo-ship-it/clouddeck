<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        return $this->boolean('email_verification_required', config('clouddeck.email_verification_required', true));
    }

    /**
     * What the header renders. Falls back to the built-in name so an instance that has
     * never opened the settings page still looks like a product rather than a blank bar.
     */
    public function branding(): array
    {
        $logo = $this->get('logo_path');

        return [
            'name' => $this->get('platform_name', config('app.name', 'CloudDeck')),
            'logo_url' => $logo && Storage::disk('public')->exists($logo) ? Storage::disk('public')->url($logo) : null,
        ];
    }

    public function forget(string $key): void
    {
        Cache::forget("system-setting:{$key}");
    }
}
