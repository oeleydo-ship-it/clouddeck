<?php

namespace App\Http\Controllers;

use App\Jobs\RemoteManagement\ApplySiteConfigurationJob;
use App\Models\Site;
use App\Models\SiteConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SiteConfigurationController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $type = $request->validate(['type' => ['required', Rule::in(['nginx', 'php'])]])['type'];
        $settings = $type === 'nginx' ? $this->nginx($request) : $this->php($request);
        $configuration = DB::transaction(function () use ($request, $site, $type, $settings): SiteConfiguration {
            $version = (int) $site->configurations()->where('type', $type)->lockForUpdate()->max('version') + 1;

            return $site->configurations()->create(['user_id' => $request->user()->id, 'type' => $type, 'version' => $version, 'settings' => $settings]);
        });
        ApplySiteConfigurationJob::dispatch($configuration->id);

        return back()->with('status', ucfirst($type).' configuration version '.$configuration->version.' queued.');
    }

    public function rollback(Request $request, SiteConfiguration $siteConfiguration): RedirectResponse
    {
        abort_unless($siteConfiguration->user_id === $request->user()->id, 404);
        $this->authorize('update', $siteConfiguration->site);
        $version = (int) $siteConfiguration->site->configurations()->where('type', $siteConfiguration->type)->max('version') + 1;
        $revision = $siteConfiguration->site->configurations()->create(['user_id' => $request->user()->id, 'type' => $siteConfiguration->type, 'version' => $version, 'settings' => $siteConfiguration->settings]);
        ApplySiteConfigurationJob::dispatch($revision->id);

        return back()->with('status', 'Rollback queued as version '.$version.'.');
    }

    private function nginx(Request $request): array
    {
        $data = $request->validate(['client_max_body_mb' => ['required', 'integer', 'between:1,1024'], 'static_cache' => ['sometimes', 'boolean'], 'include_www' => ['sometimes', 'boolean']]);

        return ['client_max_body_mb' => (int) $data['client_max_body_mb'], 'static_cache' => $request->boolean('static_cache'), 'include_www' => $request->boolean('include_www')];
    }

    private function php(Request $request): array
    {
        $data = $request->validate(['memory_limit_mb' => ['required', 'integer', 'between:64,2048'], 'upload_max_mb' => ['required', 'integer', 'between:1,1024'], 'post_max_mb' => ['required', 'integer', 'between:1,1024', 'gte:upload_max_mb'], 'max_execution_time' => ['required', 'integer', 'between:10,1800'], 'max_children' => ['required', 'integer', 'between:4,100'], 'display_errors' => ['sometimes', 'boolean']]);

        return [...$data, 'display_errors' => $request->boolean('display_errors')];
    }
}
