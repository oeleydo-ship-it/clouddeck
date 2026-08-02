<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

final class SystemSettings
{
    public function boolean(string $key, bool $default = false): bool
    {
        $value = Cache::remember("system-setting:{$key}", 60, fn () => SystemSetting::whereKey($key)->first()?->value);

        return $value === null ? $default : in_array($value, ['1', 'true', 1, true], true);
    }

    public function emailVerificationRequired(): bool
    {
        return $this->boolean('email_verification_required', config('clouddeck.email_verification_required', true));
    }

    public function forget(string $key): void
    {
        Cache::forget("system-setting:{$key}");
    }
}
