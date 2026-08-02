<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagedDatabaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'server_id' => $this->server_id, 'site_id' => $this->site_id, 'engine' => $this->engine, 'name' => $this->name, 'username' => $this->username, 'status' => $this->status, 'failure_reason' => $this->failure_reason, 'created_at' => $this->created_at];
    }
}
