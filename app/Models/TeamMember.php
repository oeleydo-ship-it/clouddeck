<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
