<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'current_period_starts_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'cancel_at_period_end' => 'boolean', 'canceled_at' => 'datetime', 'ended_at' => 'datetime', 'provider_metadata' => 'encrypted:array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function isEntitled(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true) && (! $this->current_period_ends_at || $this->current_period_ends_at->isFuture());
    }
}
