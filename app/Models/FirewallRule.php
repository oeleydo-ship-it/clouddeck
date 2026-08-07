<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirewallRule extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function ufwComment(): string
    {
        return 'uplary-fw-'.$this->id;
    }
}
