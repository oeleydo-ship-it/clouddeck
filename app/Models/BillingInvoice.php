<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['provider_metadata' => 'encrypted:array', 'period_starts_at' => 'datetime', 'period_ends_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
