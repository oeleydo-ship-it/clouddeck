<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'server_id' => $this->server_id, 'managed_database_id' => $this->managed_database_id, 'name' => $this->name, 'type' => $this->type, 'frequency' => $this->frequency, 'run_at' => $this->run_at, 'timezone' => $this->timezone, 'weekday' => $this->weekday, 'day_of_month' => $this->day_of_month, 'retention_count' => $this->retention_count, 'enabled' => $this->enabled, 'next_run_at' => $this->next_run_at, 'last_run_at' => $this->last_run_at];
    }
}
