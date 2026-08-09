<?php

namespace App\Auth;

use App\Models\User;
use App\Services\ImpersonationManager;
use Illuminate\Support\Facades\Gate;

final class ImpersonationGates
{
    public static function register(): void
    {
        Gate::define('users.impersonate', function (User $user): bool {
            // Platform permission: only super admins receive it by default.
            return $user->isSuperAdmin();
        });

        Gate::define('users.impersonate_admins', function (User $user): bool {
            if (! $user->isSuperAdmin()) {
                return false;
            }

            return app(ImpersonationManager::class)->allowImpersonateAdmins();
        });
    }
}
