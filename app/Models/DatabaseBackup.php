<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function database()
    {
        return $this->belongsTo(ManagedDatabase::class, 'managed_database_id');
    }

    public function policy()
    {
        return $this->belongsTo(BackupPolicy::class, 'backup_policy_id');
    }

    public function restores()
    {
        return $this->hasMany(BackupRestore::class);
    }
}
