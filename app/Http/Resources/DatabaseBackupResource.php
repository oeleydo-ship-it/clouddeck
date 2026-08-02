<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseBackupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'managed_database_id' => $this->managed_database_id, 'backup_policy_id' => $this->backup_policy_id, 'source' => $this->source, 'status' => $this->status, 'size' => $this->size, 'checksum' => $this->checksum, 'completed_at' => $this->completed_at, 'created_at' => $this->created_at];
    }
}
