<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'cpu_percent' => $this->cpu_percent,
            'memory_percent' => $this->memory_percent,
            'disk_percent' => $this->disk_percent,
            'load_average' => $this->load_average,
            'memory_used_bytes' => $this->memory_used_bytes,
            'memory_total_bytes' => $this->memory_total_bytes,
            'disk_used_bytes' => $this->disk_used_bytes,
            'disk_total_bytes' => $this->disk_total_bytes,
            'network_rx_bytes' => $this->network_rx_bytes,
            'network_tx_bytes' => $this->network_tx_bytes,
            'services' => $this->services,
            'processes' => $this->processes,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
