<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'site_id' => $this->site_id, 'status' => $this->status->value, 'trigger' => $this->trigger, 'release' => $this->release, 'commit_hash' => $this->commit_hash, 'commit_message' => $this->commit_message, 'progress' => $this->progress, 'exit_code' => $this->exit_code, 'started_at' => $this->started_at, 'finished_at' => $this->finished_at, 'duration_ms' => $this->duration_ms, 'logs' => $this->whenLoaded('logs', fn () => $this->logs->map->only(['id', 'level', 'output', 'created_at']))];
    }
}
