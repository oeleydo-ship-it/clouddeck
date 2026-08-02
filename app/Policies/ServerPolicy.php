<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use App\Services\TeamAccess;

class ServerPolicy
{
    public function __construct(private readonly TeamAccess $teams) {}

    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return $server->user_id === $user->id || $this->teams->canView($user, $server->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Server $server): bool
    {
        return $server->team_id ? $this->teams->canOperate($user, $server->team_id) : $server->user_id === $user->id;
    }

    public function delete(User $user, Server $server): bool
    {
        return $server->team_id ? $this->teams->canManage($user, $server->team_id) : $server->user_id === $user->id;
    }

    public function transfer(User $user, Server $server): bool
    {
        return $this->delete($user, $server);
    }
}
