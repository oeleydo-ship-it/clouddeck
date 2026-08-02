<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['monitoring_secret'];

    protected function casts(): array
    {
        return ['status' => ServerStatus::class, 'metadata' => 'array', 'provisioned_at' => 'datetime', 'monitoring_secret' => 'encrypted', 'monitoring_enabled' => 'boolean', 'last_seen_at' => 'datetime', 'phpmyadmin_enabled' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('team_id', $user->teamMemberships()->whereNotNull('accepted_at')->select('team_id'));
        });
    }

    public function cloudAccount()
    {
        return $this->belongsTo(CloudAccount::class);
    }

    public function sshKey()
    {
        return $this->belongsTo(SshKey::class);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function metrics()
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function latestMetric()
    {
        return $this->hasOne(ServerMetric::class)->latestOfMany('recorded_at');
    }

    public function databases()
    {
        return $this->hasMany(ManagedDatabase::class);
    }

    public function cronJobs()
    {
        return $this->hasMany(CronJob::class);
    }

    public function operations()
    {
        return $this->hasMany(ServerOperation::class);
    }

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }

    public function alertIncidents()
    {
        return $this->hasMany(AlertIncident::class);
    }

    public function backupPolicies()
    {
        return $this->hasMany(BackupPolicy::class);
    }

    public function snapshots()
    {
        return $this->hasMany(ServerSnapshot::class);
    }
}
