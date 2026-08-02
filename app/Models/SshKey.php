<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SshKey extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['private_key'];

    protected function casts(): array
    {
        return ['private_key' => 'encrypted', 'private_key_downloaded_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }
}
