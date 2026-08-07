<?php

namespace App\Services;

use App\Models\SecurityDetectionSetting;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SecurityDetectionSettings
{
    public function __construct(private readonly TeamAccess $teams) {}

    /**
     * @return array{user_id: int|null, team_id: string|null, label: string}
     */
    public function scopeFor(User $user): array
    {
        $team = $user->currentTeam;
        if ($team && ($user->isSuperAdmin() || $this->teams->canView($user, $team))) {
            return ['user_id' => null, 'team_id' => $team->id, 'label' => $team->name];
        }

        return ['user_id' => $user->id, 'team_id' => null, 'label' => 'Personal resources'];
    }

    public function canManage(User $user): bool
    {
        $scope = $this->scopeFor($user);

        return $user->isSuperAdmin()
            || ! $scope['team_id']
            || $this->teams->canManage($user, $scope['team_id']);
    }

    /**
     * Inventory and scan targets use the same accessibility scope as the rest of the
     * console (personal + team memberships). Detection settings remain workspace-scoped
     * via scopeFor()/forServer(); they must not hide accessible personal servers when
     * the user is viewing a team workspace.
     */
    public function serverQueryFor(User $user): Builder
    {
        return Server::query()->accessibleTo($user);
    }

    /**
     * @return array{enabled: bool, rules: array<string, array<string, mixed>>, scope: array<string, mixed>, model: SecurityDetectionSetting|null}
     */
    public function forUser(User $user): array
    {
        $scope = $this->scopeFor($user);

        return $this->resolved($this->find($scope), $scope);
    }

    /**
     * @return array{enabled: bool, rules: array<string, array<string, mixed>>, scope: array<string, mixed>, model: SecurityDetectionSetting|null}
     */
    public function forServer(Server $server): array
    {
        $scope = $server->team_id
            ? ['user_id' => null, 'team_id' => $server->team_id, 'label' => $server->team?->name ?? 'Team resources']
            : ['user_id' => $server->user_id, 'team_id' => null, 'label' => 'Personal resources'];

        return $this->resolved($this->find($scope), $scope);
    }

    public function enabledForServer(Server $server): bool
    {
        return (bool) config('security-detection.enabled', true) && $this->forServer($server)['enabled'];
    }

    public function maxLookbackForServer(Server $server): int
    {
        $settings = $this->forServer($server);

        return (int) collect($settings['rules'])
            ->where('enabled', true)
            ->max('lookback_minutes') ?: (int) config('security-detection.scan_interval_minutes', 5);
    }

    public function saveFor(User $user, bool $enabled, array $overrides): SecurityDetectionSetting
    {
        $scope = $this->scopeFor($user);

        return SecurityDetectionSetting::query()->updateOrCreate(
            array_filter([
                'user_id' => $scope['user_id'],
                'team_id' => $scope['team_id'],
            ], fn ($value) => $value !== null),
            [
                'user_id' => $scope['user_id'],
                'team_id' => $scope['team_id'],
                'enabled' => $enabled,
                'rule_overrides' => $overrides,
            ],
        );
    }

    public function resetFor(User $user): ?SecurityDetectionSetting
    {
        $setting = $this->find($this->scopeFor($user));
        $setting?->delete();

        return $setting;
    }

    /**
     * @param  array{user_id: int|null, team_id: string|null}  $scope
     */
    private function find(array $scope): ?SecurityDetectionSetting
    {
        return SecurityDetectionSetting::query()
            ->when(
                $scope['team_id'],
                fn (Builder $query, string $teamId) => $query->where('team_id', $teamId),
                fn (Builder $query) => $query->where('user_id', $scope['user_id'])->whereNull('team_id'),
            )
            ->first();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{enabled: bool, rules: array<string, array<string, mixed>>, scope: array<string, mixed>, model: SecurityDetectionSetting|null}
     */
    private function resolved(?SecurityDetectionSetting $setting, array $scope): array
    {
        $rules = collect(config('security-detection.rules', []))
            ->mapWithKeys(function (array $default, string $key) use ($setting): array {
                $overrides = $setting?->rule_overrides ?? [];
                $override = (array) ($overrides[$key] ?? []);
                $allowed = array_intersect_key($override, array_flip(['enabled', 'threshold', 'lookback_minutes', 'severity']));

                return [$key => array_replace($default, $allowed)];
            })
            ->all();

        return [
            'enabled' => (bool) config('security-detection.enabled', true) && ($setting?->enabled ?? true),
            'rules' => $rules,
            'scope' => $scope,
            'model' => $setting,
        ];
    }
}
