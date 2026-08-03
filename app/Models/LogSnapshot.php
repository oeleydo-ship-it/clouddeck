<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LogSnapshot extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lines' => 'integer'];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
