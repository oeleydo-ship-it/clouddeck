<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BackupRestore extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function backup() { return $this->belongsTo(DatabaseBackup::class, 'database_backup_id'); }
    public function database() { return $this->belongsTo(ManagedDatabase::class, 'managed_database_id'); }
}
