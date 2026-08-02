<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ServerSnapshot extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size_gigabytes' => 'decimal:2', 'provider_created_at' => 'datetime', 'completed_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function server() { return $this->belongsTo(Server::class); }
    public function policy() { return $this->belongsTo(BackupPolicy::class, 'backup_policy_id'); }
}
