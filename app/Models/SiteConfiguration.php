<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SiteConfiguration extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'encrypted:array', 'applied_at' => 'datetime'];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
