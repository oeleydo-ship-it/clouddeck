<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'server_id' => $this->server_id, 'backup_policy_id' => $this->backup_policy_id, 'name' => $this->name, 'status' => $this->status, 'size_gigabytes' => $this->size_gigabytes, 'provider_created_at' => $this->provider_created_at, 'completed_at' => $this->completed_at, 'created_at' => $this->created_at];
    }
}
