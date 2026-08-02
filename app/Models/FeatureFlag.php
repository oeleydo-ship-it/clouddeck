<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function overrides()
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }
}
