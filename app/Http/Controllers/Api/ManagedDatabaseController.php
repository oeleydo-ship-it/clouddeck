<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagedDatabaseResource;
use App\Jobs\Operations\CreateDatabaseJob;
use App\Jobs\Operations\DeleteDatabaseJob;
use App\Models\ManagedDatabase;
use App\Models\Server;
use App\Services\QuotaManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagedDatabaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ManagedDatabaseResource::collection(ManagedDatabase::where('user_id', $request->user()->id)->latest()->paginate());
    }

    public function store(Request $request, QuotaManager $quotas): JsonResponse
    {
        $quotas->assertCanCreate($request->user(), 'databases');
        $data = $request->validate(['server_id' => ['required', 'uuid', Rule::exists('servers', 'id')->where('user_id', $request->user()->id)], 'site_id' => ['nullable', 'uuid', Rule::exists('sites', 'id')->where('user_id', $request->user()->id)], 'engine' => ['required', Rule::in(['mysql', 'postgresql'])], 'name' => ['required', 'regex:/^[a-z][a-z0-9_]{0,62}$/'], 'username' => ['required', 'regex:/^[a-z][a-z0-9_]{0,30}$/']]);
        $server = Server::findOrFail($data['server_id']);
        if (! empty($data['site_id']) && ! $server->sites()->whereKey($data['site_id'])->exists()) {
            abort(422, 'The site must belong to the selected server.');
        }$password = Str::random(32);
        $database = $server->databases()->create([...$data, 'user_id' => $request->user()->id, 'password' => $password]);
        CreateDatabaseJob::dispatch($database->id)->onQueue('operations');

        return response()->json(['data' => (new ManagedDatabaseResource($database))->resolve(), 'password' => $password], 201);
    }

    public function destroy(Request $request, ManagedDatabase $managedDatabase): Response
    {
        $this->authorize('update', $managedDatabase->server);
        $managedDatabase->update(['status' => 'deleting']);
        DeleteDatabaseJob::dispatch($managedDatabase->id)->onQueue('operations');

        return response()->noContent();
    }
}
