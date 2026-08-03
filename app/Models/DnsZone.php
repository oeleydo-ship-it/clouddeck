<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DnsZone extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(DnsAccount::class, 'dns_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The sites this zone could be pointing at. Matched on the domain and its subdomains,
     * so a zone for example.com claims app.example.com but never notexample.com.
     */
    public function sites()
    {
        return Site::query()
            ->where('user_id', $this->user_id)
            ->where(fn ($query) => $query->where('domain', $this->name)->orWhere('domain', 'like', '%.'.$this->name));
    }
}
