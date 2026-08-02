<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AlertIncident extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'float', 'threshold' => 'float', 'started_at' => 'datetime', 'last_notified_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function rule()
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
