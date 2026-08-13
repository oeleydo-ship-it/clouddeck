<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'server_id' => $this->server_id, 'domain' => $this->domain, 'platform' => $this->platform, 'php_version' => $this->php_version, 'repository_url' => $this->repository_url, 'branch' => $this->branch, 'status' => $this->status, 'auto_deploy' => $this->auto_deploy, 'zero_downtime' => $this->zero_downtime, 'last_deployed_at' => $this->last_deployed_at, 'server' => new ServerResource($this->whenLoaded('server')), 'latest_deployment' => new DeploymentResource($this->whenLoaded('latestDeployment')), 'created_at' => $this->created_at];
    }
}
