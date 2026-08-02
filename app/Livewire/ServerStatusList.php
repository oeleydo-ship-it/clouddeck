<?php

namespace App\Livewire;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Support\Collection;
use Livewire\Component;

class ServerStatusList extends Component
{
    /** @var array<int, string> */
    public array $serverIds = [];

    public function mount(Collection $servers): void
    {
        $this->serverIds = $servers->pluck('id')->all();
    }

    /**
     * One subscription per visible server. Livewire's #[On] attribute binds a single
     * channel from a property, which cannot express a list, so the listeners are built
     * here instead.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return collect($this->serverIds)
            ->mapWithKeys(fn (string $id) => ['echo-private:servers.'.$id.',.provisioning-updated' => 'refresh'])
            ->all();
    }

    public function refresh(): void
    {
        // Re-render reads current rows; the broadcast only wakes the component up.
    }

    public function render()
    {
        $servers = Server::whereIn('id', $this->serverIds)
            ->with(['team', 'sites'])
            ->latest()
            ->get();

        return view('livewire.server-status-list', [
            'servers' => $servers,
            // Keep polling while anything is mid-flight, so a dropped WebSocket still
            // finishes the story rather than freezing the bar at whatever it last saw.
            'active' => $servers->contains(fn (Server $server) => ! in_array($server->status, [ServerStatus::Ready, ServerStatus::Failed], true)),
        ]);
    }
}
