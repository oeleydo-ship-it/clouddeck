<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['limits' => 'array', 'features' => 'array', 'active' => 'boolean', 'public' => 'boolean'];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureOverrides()
    {
        return $this->hasMany(FeatureFlagOverride::class);
    }

    public function formattedPrice(string $field = 'monthly_price'): string
    {
        $cents = (int) $this->{$field};
        $currency = strtoupper((string) ($this->currency ?: 'USD'));
        if ($cents === 0) {
            return 'Free';
        }

        return $currency.' '.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    /**
     * @return list<string>
     */
    public function quotaLines(bool $managedServersEnabled): array
    {
        $limits = $this->limits ?? [];
        $labels = $managedServersEnabled
            ? [
                'servers' => 'BYOS servers',
                'managed_servers' => 'managed servers',
                'sites' => 'BYOS sites',
                'managed_sites' => 'managed sites',
                'databases' => 'databases',
                'api_tokens' => 'API tokens',
                'teams' => 'teams',
                'team_members' => 'team members',
            ]
            : [
                'servers' => 'servers',
                'sites' => 'sites',
                'databases' => 'databases',
                'api_tokens' => 'API tokens',
                'teams' => 'teams',
                'team_members' => 'team members',
            ];

        $lines = [];
        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $limits)) {
                continue;
            }
            $value = $limits[$key] < 0 ? 'Unlimited' : $limits[$key];
            $lines[] = $value.' '.$label;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function enabledFeatureLabels(): array
    {
        $catalog = config('plan-features.labels', []);
        $labels = [];
        foreach ($this->features ?? [] as $key => $enabled) {
            if ($enabled && isset($catalog[$key])) {
                $labels[] = $catalog[$key];
            }
        }

        return $labels;
    }
}
