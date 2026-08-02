<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }
}
