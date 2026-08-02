<?php

namespace App\Http\Controllers;

use App\Actions\Deployments\StartDeployment;
use App\Enums\DeploymentStatus;
use App\Events\DeploymentFinished;
use App\Models\Deployment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DeploymentController extends Controller
{
    public function show(Request $request, Deployment $deployment): View
    {
        $deployment->load('site.server');
        $this->authorize('view', $deployment->site);

        return view('deployments.show', ['deployment' => $deployment]);
    }

    /**
     * A deployment that never reaches a worker stays pending for good, and because a site
     * refuses to start a second one while any is in progress, that single row locks the site
     * out of deploying at all. Cancelling is the way back out without database surgery.
     */
    public function cancel(Request $request, Deployment $deployment, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('deploy', $deployment->site);

        // The page updates its status live but was rendered with whatever was true at load,
        // so the button can outlive the deployment it belongs to.
        if (! in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true)) {
            return back()->withErrors(['deployment' => 'This deployment has already finished.']);
        }

        $deployment->update([
            'status' => DeploymentStatus::Cancelled,
            'finished_at' => now(),
            'duration_ms' => $deployment->started_at?->diffInMilliseconds(now()),
        ]);
        $deployment->logs()->create([
            'level' => 'error',
            'output' => 'Cancelled by '.($request->user()->name ?: $request->user()->email).'.',
            'created_at' => now(),
        ]);

        $audit->record($request, 'deployment.cancelled', $deployment->site, [], ['deployment_id' => $deployment->id]);

        // Best-effort: the page listens for this, but a broadcaster being down must not turn
        // a cancellation into an error.
        try {
            DeploymentFinished::dispatch($deployment->fresh(['site.user']));
        } catch (Throwable) {
        }

        return back()->with('status', 'Deployment cancelled. You can deploy again now.');
    }

    public function retry(Request $request, Deployment $deployment, StartDeployment $start): RedirectResponse
    {
        $this->authorize('deploy', $deployment->site);

        $created = $start->execute($deployment->site, $request->user());

        return redirect()->route('deployments.show', $created)->with('status', 'Deployment queued.');
    }
}
