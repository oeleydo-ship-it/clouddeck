<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $servers = $request->user()->accessibleServers();

        // Per-resource CPU/memory/disk live on each server's own page, not summarised here.
        $monitoredServers = (clone $servers)->where('monitoring_enabled', true)->get(['id', 'last_seen_at']);

        return view('dashboard', [
            'stats' => [
                'servers' => (clone $servers)->count(),
                'active' => (clone $servers)->whereIn('status', [ServerStatus::Active, ServerStatus::Ready])->count(),
                'deployments' => Deployment::whereHas('site.server', fn ($query) => $query->accessibleTo($request->user()))->whereDate('created_at', today())->count(),
                'failed' => Deployment::whereHas('site.server', fn ($query) => $query->accessibleTo($request->user()))->where('status', DeploymentStatus::Failed)->count(),
                'offline' => $monitoredServers->filter(fn ($server) => ! $server->last_seen_at || $server->last_seen_at->lt(now()->subMinutes(5)))->count(),
            ],
        ]);
    }
}
