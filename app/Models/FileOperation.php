<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FileOperation extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $hidden = ['payload', 'result', 'storage_path'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'result' => 'encrypted', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
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
