<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => DeploymentStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(DeploymentLog::class);
    }

    public function getDurationForHumansAttribute(): ?string
    {
        return $this->duration_ms === null ? null : number_format($this->duration_ms / 1000, 2).'s';
    }
}
