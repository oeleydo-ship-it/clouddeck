<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeploymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return DeploymentResource::collection(Deployment::whereHas('site', fn ($query) => $query->where('user_id', $request->user()->id))->latest()->paginate());
    }

    public function show(Request $request, Deployment $deployment): DeploymentResource
    {
        $this->authorize('view', $deployment->site);

        return new DeploymentResource($deployment->load('logs'));
    }
}
