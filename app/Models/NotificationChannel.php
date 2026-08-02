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
        return ['configuration' => 'encrypted:array', 'enabled' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
