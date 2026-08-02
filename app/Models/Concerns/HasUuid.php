<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasUuid
{
    use HasUuids;

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
