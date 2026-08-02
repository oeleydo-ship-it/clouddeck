<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SslCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'site_id' => $this->site_id, 'domains' => $this->domains, 'provider' => $this->provider, 'status' => $this->status, 'auto_renew' => $this->auto_renew, 'force_https' => $this->force_https, 'issued_at' => $this->issued_at, 'expires_at' => $this->expires_at, 'failure_reason' => $this->failure_reason];
    }
}
