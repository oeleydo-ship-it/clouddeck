<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerMetricResource;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MetricController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['server_id' => ['required', 'uuid'], 'hours' => ['nullable', 'integer', 'between:1,168']]);
        $server = Server::where('user_id', $request->user()->id)->findOrFail($data['server_id']);

        return ServerMetricResource::collection($server->metrics()->where('recorded_at', '>=', now()->subHours($data['hours'] ?? 24))->latest('recorded_at')->paginate(288));
    }
}
