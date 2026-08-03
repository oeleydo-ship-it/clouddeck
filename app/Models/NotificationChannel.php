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
     * Which events reach this destination. Every event CloudDeck can raise; a channel that
     * names none of them is subscribed to all, so one created before events existed keeps
     * behaving as it did.
     */
    public const EVENTS = [
        'deploy_complete' => 'Deploy complete',
        'server_down' => 'Server down',
        'ssl_expiring' => 'SSL expiring',
        'disk_full' => 'Disk full',
        'queue_failed' => 'Queue failed',
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
