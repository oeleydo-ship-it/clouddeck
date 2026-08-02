<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class Installation
{
    /**
     * An instance counts as installed once anybody owns it. That is deliberately a fact
     * about the data rather than a marker file: a redeployment replaces the release
     * directory but never the database, so an installed instance stays installed.
     *
     * Note this asks whether any account exists, not whether an administrator exists. Those
     * differ exactly when an instance has customers but no super admin — after the last
     * administrator is demoted or deleted — and treating that as "not installed" would
     * reopen the wizard on a live instance and let the next visitor claim super admin over
     * somebody else's servers. Recovering from a lost administrator is a console job, not
     * something the public internet gets to do.
     */
    public function isInstalled(): bool
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('system_settings')) {
                return false;
            }
        } catch (Throwable) {
            // No usable database yet — mid-migration, or credentials not written. Callers
            // treat this as "not installed"; the installer itself will surface the error.
            return false;
        }

        // Instances that predate the installer carry no marker, so any account closes it.
        return SystemSetting::whereKey('installed_at')->exists() || User::exists();
    }

    public function completedAt(): ?string
    {
        return SystemSetting::whereKey('installed_at')->first()?->value;
    }
}
