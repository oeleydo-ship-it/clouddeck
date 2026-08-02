<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

final class TeamAccess
{
    public function role(User $user, Team|string|null $team): ?string
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        if (! $teamId) {
            return null;
        }

        return $user->teamMemberships()
            ->where('team_id', $teamId)
            ->whereNotNull('accepted_at')
            ->value('role');
    }

    public function canView(User $user, Team|string|null $team): bool
    {
        return $this->role($user, $team) !== null;
    }

    public function canOperate(User $user, Team|string|null $team): bool
    {
        return in_array($this->role($user, $team), ['owner', 'admin', 'operator'], true);
    }

    public function canManage(User $user, Team|string|null $team): bool
    {
        return in_array($this->role($user, $team), ['owner', 'admin'], true);
    }
}
