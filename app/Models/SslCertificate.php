<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SslCertificate extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['domains' => 'array', 'auto_renew' => 'boolean', 'force_https' => 'boolean', 'issued_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
