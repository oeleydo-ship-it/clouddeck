<?php

namespace App\Http\Controllers\Api;

use App\Actions\Servers\ProvisionServer;
use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Services\QuotaManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ServerResource::collection($request->user()->accessibleServers()->with(['cloudAccount', 'sites', 'team'])->latest()->paginate());
    }

    public function store(StoreServerRequest $request, ProvisionServer $provision, QuotaManager $quotas): ServerResource
    {
        $quotas->assertCanCreate($request->user(), 'servers');
        $teamId = $request->user()->currentTeam?->memberships()->where('user_id', $request->user()->id)->whereNotNull('accepted_at')->exists()
            ? $request->user()->current_team_id
            : null;
        $server = $request->user()->servers()->create([...$request->validated(), 'team_id' => $teamId, 'status' => ServerStatus::Pending]);
        $provision->execute($server);

        return new ServerResource($server->load('cloudAccount'));
    }

    public function show(Request $request, Server $server): ServerResource
    {
        $this->authorize('view', $server);

        return new ServerResource($server->load(['cloudAccount', 'sites']));
    }

    public function action(Request $request, Server $server, CloudProviderManager $manager): ServerResource
    {
        $this->authorize('update', $server);
        $data = $request->validate(['action' => ['required', 'in:reboot,shutdown,power_on,snapshot'], 'name' => ['nullable', 'string', 'max:100']]);
        $manager->for($server->cloudAccount)->action($server->provider_id, $data['action'], $data['name'] ? ['name' => $data['name']] : []);

        return new ServerResource($server);
    }

    public function destroy(Request $request, Server $server, CloudProviderManager $manager): Response
    {
        $this->authorize('delete', $server);
        if ($server->provider_id) {
            $manager->for($server->cloudAccount)->deleteServer($server->provider_id);
        }$server->delete();

        return response()->noContent();
    }
}
