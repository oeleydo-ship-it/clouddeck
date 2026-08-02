<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminFeatureFlagController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'alpha_dash', 'max:100', 'unique:feature_flags,key'], 'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'rollout_percentage' => ['required', 'integer', 'between:0,100'], 'enabled' => ['sometimes', 'boolean']]);
        $flag = FeatureFlag::create([...$data, 'enabled' => $request->boolean('enabled')]);
        $audit->record($request, 'feature_flag.created', $flag, [], $data);

        return back()->with('status', 'Feature flag created.');
    }

    public function update(Request $request, FeatureFlag $featureFlag, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'rollout_percentage' => ['required', 'integer', 'between:0,100'], 'enabled' => ['sometimes', 'boolean']]);
        $old = $featureFlag->only(['name', 'description', 'rollout_percentage', 'enabled']);
        $featureFlag->update([...$data, 'enabled' => $request->boolean('enabled')]);
        Cache::forget('feature-flag:'.$featureFlag->key);
        $audit->record($request, 'feature_flag.updated', $featureFlag, $old, $featureFlag->only(['name', 'description', 'rollout_percentage', 'enabled']));

        return back()->with('status', 'Feature flag updated.');
    }
}
