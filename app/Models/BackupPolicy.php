<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackupPolicy extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function server() { return $this->belongsTo(Server::class); }
    public function database() { return $this->belongsTo(ManagedDatabase::class, 'managed_database_id'); }
    public function databaseBackups() { return $this->hasMany(DatabaseBackup::class); }
    public function snapshots() { return $this->hasMany(ServerSnapshot::class); }
}
