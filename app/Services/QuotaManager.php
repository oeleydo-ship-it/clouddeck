<?php

namespace App\Services;

use App\Models\ManagedDatabase;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class QuotaManager
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function assertCanCreate(User $user, string $resource, int $amount = 1): void
    {
        $limit = $this->entitlements->limit($user, $resource);
        if ($limit >= 0 && $this->usage($user, $resource) + $amount > $limit) {
            throw ValidationException::withMessages(['quota' => 'Your plan limit for '.$resource.' has been reached. Upgrade or remove an existing resource.']);
        }
    }

    public function usage(User $user, string $resource): int
    {
        return match ($resource) {
            'servers' => $user->servers()->count(),
            'sites' => $user->sites()->count(),
            'databases' => ManagedDatabase::where('user_id', $user->id)->count(),
            'api_tokens' => $user->tokens()->count(),
            'teams' => $user->ownedTeams()->count(),
            'team_members' => $user->ownedTeams()->withCount('memberships')->get()->sum('memberships_count'),
            default => 0,
        };
    }
}
