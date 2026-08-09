<?php

namespace App\Services;

use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class QuotaManager
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function assertCanCreate(User $user, string $resource, int $amount = 1): void
    {
        $limit = $this->entitlements->limit($user, $resource);
        if ($limit >= 0 && $this->usage($user, $resource) + $amount > $limit) {
            $label = match ($resource) {
                'managed_servers' => 'managed servers',
                'servers' => 'BYOS servers',
                'sites' => 'BYOS sites',
                'managed_sites' => 'managed sites',
                'os_backup_gb' => 'OS backup storage (GB)',
                default => $resource,
            };
            $hint = $resource === 'os_backup_gb'
                ? ' Buy more GB on Billing, upgrade your plan, or delete older snapshots.'
                : ' Upgrade or remove an existing resource.';
            throw ValidationException::withMessages(['quota' => 'Your plan limit for '.$label.' has been reached.'.$hint]);
        }
    }

    /**
     * Site quotas are split by host type: BYOS/custom servers use `sites`, platform-managed
     * hosts use `managed_sites`, so a plan can allow e.g. 1 BYOS site and 5 managed sites.
     */
    public function assertCanCreateSite(User $user, Server $server, int $amount = 1): void
    {
        $this->assertCanCreate($user, $server->isManaged() ? 'managed_sites' : 'sites', $amount);
    }

    public function usage(User $user, string $resource): int
    {
        return match ($resource) {
            // BYOS: customer cloud API provision, imports, and custom SSH attaches.
            'servers' => $user->servers()->where(function ($query) {
                $query->whereNull('provisioning_source')
                    ->orWhere('provisioning_source', '!=', 'managed');
            })->count(),
            'managed_servers' => $user->servers()->where('provisioning_source', 'managed')->count(),
            // Sites on BYOS / custom hosts (everything that is not platform-managed).
            'sites' => $user->sites()->whereHas('server', function ($query) {
                $query->whereNull('provisioning_source')
                    ->orWhere('provisioning_source', '!=', 'managed');
            })->count(),
            'managed_sites' => $user->sites()->whereHas('server', function ($query) {
                $query->where('provisioning_source', 'managed');
            })->count(),
            'databases' => ManagedDatabase::where('user_id', $user->id)->count(),
            'api_tokens' => $user->tokens()->count(),
            'teams' => $user->ownedTeams()->count(),
            'team_members' => $user->ownedTeams()->withCount('memberships')->get()->sum('memberships_count'),
            // Ready snapshots use recorded GB; in-flight ones reserve at least 1 GB until sized.
            'os_backup_gb' => (int) ServerSnapshot::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['ready', 'creating', 'processing'])
                ->get(['size_gigabytes', 'status'])
                ->sum(function (ServerSnapshot $snapshot): int {
                    $size = (float) ($snapshot->size_gigabytes ?? 0);
                    if ($size > 0) {
                        return (int) ceil($size);
                    }

                    return in_array($snapshot->status, ['creating', 'processing'], true) ? 1 : 0;
                }),
            default => 0,
        };
    }
}
