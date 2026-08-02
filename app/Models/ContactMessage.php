<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
