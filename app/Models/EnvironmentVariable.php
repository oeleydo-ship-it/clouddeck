<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class EnvironmentVariable extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'is_secret' => 'boolean'];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
