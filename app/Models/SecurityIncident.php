<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('team_id', $user->teamMemberships()->whereNotNull('accepted_at')->select('team_id'));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function firewallRule()
    {
        return $this->belongsTo(FirewallRule::class);
    }
}
