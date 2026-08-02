<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BillingWebhookEvent extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];
    }
}
