<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['limits' => 'array', 'features' => 'array', 'active' => 'boolean', 'public' => 'boolean'];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureOverrides()
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }
}
