<?php

namespace App\Http\Controllers\Api;

use App\Actions\Deployments\StartDeployment;
use App\Enums\ServerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Resources\DeploymentResource;
use App\Http\Resources\SiteResource;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Models\Site;
use App\Services\QuotaManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SiteResource::collection($request->user()->sites()->with(['server', 'latestDeployment'])->latest()->paginate());
    }

    public function store(StoreSiteRequest $request, QuotaManager $quotas): SiteResource
    {
        $quotas->assertCanCreate($request->user(), 'sites');
        $site = DB::transaction(function () use ($request) {
            $site = $request->user()->sites()->create([...$request->validated(), 'auto_deploy' => $request->boolean('auto_deploy'), 'zero_downtime' => $request->boolean('zero_downtime', true), 'webhook_secret' => Str::random(64), 'status' => 'configuring']);
            $site->environmentVariables()->createMany(collect(['APP_NAME' => $site->domain, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.$site->domain, 'APP_KEY' => ''])->map(fn ($value, $key) => ['key' => $key, 'value' => $value, 'is_secret' => $key === 'APP_KEY'])->values()->all());

            return $site;
        });
        ConfigureSiteJob::dispatch($site->id)->onQueue('provisioning');

        return new SiteResource($site->load('server'));
    }

    public function show(Request $request, Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load(['server', 'latestDeployment']));
    }

    public function deploy(Request $request, Site $site, StartDeployment $start): DeploymentResource
    {
        $this->authorize('deploy', $site);
        abort_unless($site->status === 'active' && $site->server->status === ServerStatus::Ready, 422, 'The site and server must be active.');

        return new DeploymentResource($start->execute($site, $request->user(), 'api'));
    }
}
