<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TerminalCommand extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $hidden = ['command', 'output'];

    protected function casts(): array
    {
        return ['command' => 'encrypted', 'output' => 'encrypted', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
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
