<?php

namespace App\Livewire;

use App\Http\Controllers\LogController;
use App\Jobs\Sites\FetchLogJob;
use App\Models\LogSnapshot;
use App\Models\Site;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LogViewer extends Component
{
    public string $siteId;

    public string $source = 'laravel';

    public int $lines = 200;

    public function mount(Site $site): void
    {
        Gate::authorize('view', $site);
        $this->siteId = $site->id;
    }

    public function read(): void
    {
        $site = Site::findOrFail($this->siteId);
        Gate::authorize('update', $site);

        // Bounds re-applied here: the browser sets these and Livewire properties are input
        // like any other.
        abort_unless(array_key_exists($this->source, LogController::SOURCES), 422);
        $this->lines = max(20, min(2000, $this->lines));

        $snapshot = $site->logSnapshots()->create([
            'server_id' => $site->server_id,
            'user_id' => auth()->id(),
            'source' => $this->source,
            'lines' => $this->lines,
            'status' => 'pending',
        ]);

        FetchLogJob::dispatch($snapshot->id)->onQueue('operations');
    }

    public function render()
    {
        $snapshot = LogSnapshot::where('site_id', $this->siteId)
            ->where('source', $this->source)
            ->latest()
            ->first();

        return view('livewire.log-viewer', [
            'snapshot' => $snapshot,
            'sources' => LogController::SOURCES,
            'running' => $snapshot && in_array($snapshot->status, ['pending', 'running'], true),
        ]);
    }
}
