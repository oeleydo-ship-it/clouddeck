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

    public bool $wasActive = true;

    public function mount(Deployment $deployment): void
    {
        Gate::authorize('view', $deployment->site);
        $this->deploymentId = $deployment->id;
        $this->wasActive = in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true);
    }

    #[On('echo-private:deployments.{deploymentId},.log-appended')]
    #[On('echo-private:deployments.{deploymentId},.deployment-finished')]
    public function refresh(): void
    {
        // Re-render pulls the latest logs/status from the database; the broadcast just wakes it up.
    }

    public function render()
    {
        $deployment = Deployment::with('site')->findOrFail($this->deploymentId);
        $active = in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true);

        // Once the run settles, tell the surrounding page to drop the "Deployment queued."
        // flash and swap Cancel for Deploy again — those were rendered outside this component.
        if ($this->wasActive && ! $active) {
            $this->wasActive = false;
            $this->dispatch('deployment-settled', status: $deployment->status->value);
        }

        return view('livewire.deployment-log-stream', [
            'deployment' => $deployment,
            'logs' => $deployment->logs()->latest()->limit(1000)->get()->reverse()->values(),
            'active' => $active,
            'canDeploy' => Gate::allows('deploy', $deployment->site),
        ]);
    }
}
