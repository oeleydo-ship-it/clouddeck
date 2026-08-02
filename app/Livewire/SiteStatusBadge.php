<?php

namespace App\Livewire;

use App\Models\Site;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteStatusBadge extends Component
{
    public string $siteId;

    /** The status the page was rendered with, so a settled site can reload it once. */
    public string $initialStatus;

    public function mount(Site $site): void
    {
        Gate::authorize('view', $site);
        $this->siteId = $site->id;
        $this->initialStatus = $site->status;
    }

    #[On('echo-private:sites.{siteId},.status-updated')]
    public function refresh(): void
    {
        // Re-render reads the row; the broadcast only wakes the component up.
    }

    public function render()
    {
        $site = Site::findOrFail($this->siteId);
        $settled = in_array($site->status, ['active', 'failed'], true);

        return view('livewire.site-status-badge', [
            'site' => $site,
            // Still working, so poll as well: a dropped socket should not leave the badge
            // saying "configuring" forever.
            'pending' => ! $settled,
            // The rest of the page was rendered for the old status — the deploy button and
            // the database notice among it — so reload once the site finishes settling.
            'reload' => $settled && $site->status !== $this->initialStatus,
        ]);
    }
}
