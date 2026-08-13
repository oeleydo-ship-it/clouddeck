<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationChannel extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['configuration'];

    protected function casts(): array
    {
        return ['configuration' => 'encrypted:array', 'events' => 'array', 'enabled' => 'boolean'];
    }

    /**
     * Every event Uplary can raise. A recipient that names none of them is subscribed to
     * all, so one created before events existed keeps behaving as it did.
     */
    public const EVENTS = [
        'server_provisioned' => 'Server provisioned',
        'server_down' => 'Server down',
        'disk_full' => 'Disk full',
        'site_added' => 'Site added',
        'site_down' => 'Website down',
        'site_recovered' => 'Website recovered',
        'dns_mismatch' => 'DNS mismatch',
        'deploy_complete' => 'Deploy complete',
        'ssl_installed' => 'SSL certificate issued',
        'ssl_removed' => 'SSL certificate removed',
        'ssl_expiring' => 'SSL certificate expiring',
        'queue_failed' => 'Queue failed',
        'backup_failed' => 'Backup failed',
        'auto_heal' => 'Auto-heal action',
        'security_incident' => 'Security incident',
    ];

    public function wantsEvent(string $event): bool
    {
        return $this->events === null || $this->events === [] || in_array($event, $this->events, true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
