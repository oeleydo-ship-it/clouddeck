<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'hostname' => $this->hostname, 'provider' => $this->cloudAccount?->provider, 'region' => $this->region, 'size' => $this->size, 'image' => $this->image, 'status' => $this->status->value, 'progress' => $this->progress, 'current_step' => $this->current_step, 'public_ip' => $this->public_ip, 'provisioned_at' => $this->provisioned_at, 'sites' => SiteResource::collection($this->whenLoaded('sites')), 'created_at' => $this->created_at];
    }
}
