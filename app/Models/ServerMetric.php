<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['services' => 'array', 'processes' => 'array', 'recorded_at' => 'datetime'];
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
