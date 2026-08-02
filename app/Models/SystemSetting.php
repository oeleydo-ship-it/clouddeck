<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'is_public' => 'boolean'];
    }
}
