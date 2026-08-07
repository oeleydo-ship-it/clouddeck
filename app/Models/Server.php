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
        return [
            'status' => ServerStatus::class,
            'metadata' => 'array',
            'provisioned_at' => 'datetime',
            'monitoring_secret' => 'encrypted',
            'monitoring_enabled' => 'boolean',
            'auto_heal_enabled' => 'boolean',
            'auto_heal_last_actions' => 'array',
            'last_seen_at' => 'datetime',
            'phpmyadmin_enabled' => 'boolean',
            'firewall_synced_at' => 'datetime',
            'security_scanned_at' => 'datetime',
        ];
    }

    public function securityScanIsBusy(): bool
    {
        if (! in_array($this->security_scan_status, ['queued', 'running'], true)) {
            return false;
        }

        // Stale queued/running rows must not permanently disable "Scan now" when a worker
        // never picked up the job (or died before updating status).
        return ! $this->securityScanIsStale();
    }

    public function securityScanIsStale(int $queuedMinutes = 10, int $runningMinutes = 20): bool
    {
        if (! in_array($this->security_scan_status, ['queued', 'running'], true)) {
            return false;
        }

        $updatedAt = $this->updated_at;
        if (! $updatedAt) {
            return true;
        }

        $limit = $this->security_scan_status === 'running'
            ? now()->subMinutes($runningMinutes)
            : now()->subMinutes($queuedMinutes);

        return $updatedAt->lt($limit);
    }

    public function markSecurityScan(string $status, ?string $message = null, bool $touchScannedAt = false): void
    {
        $attributes = [
            'security_scan_status' => $status,
            'security_scan_message' => $message,
        ];

        if ($touchScannedAt) {
            $attributes['security_scanned_at'] = now();
        }

        $this->forceFill($attributes)->save();
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

    public function firewallRules()
    {
        return $this->hasMany(FirewallRule::class);
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

    public function securityIncidents()
    {
        return $this->hasMany(SecurityIncident::class);
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
