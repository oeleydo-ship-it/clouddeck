<?php

namespace App\Livewire;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class DeploymentLogStream extends Component
{
    public string $deploymentId;

    public function mount(Deployment $deployment): void
    {
        Gate::authorize('view', $deployment->site);
        $this->deploymentId = $deployment->id;
    }

    #[On('echo-private:deployments.{deploymentId},.log-appended')]
    public function refresh(): void
    {
        // Re-render pulls the latest logs/status from the database; the broadcast just wakes it up.
    }

    public function render()
    {
        $deployment = Deployment::findOrFail($this->deploymentId);

        return view('livewire.deployment-log-stream', [
            'deployment' => $deployment,
            'logs' => $deployment->logs()->latest()->limit(1000)->get()->reverse()->values(),
            'active' => in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true),
        ]);
    }
}
