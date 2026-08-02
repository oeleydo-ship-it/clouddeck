<?php

use App\Models\Deployment;
use App\Models\Server;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('servers.{serverId}', function ($user, string $serverId) {
    $server = Server::find($serverId);

    return $server && Gate::forUser($user)->allows('view', $server);
});

Broadcast::channel('deployments.{deploymentId}', function ($user, string $deploymentId) {
    $deployment = Deployment::find($deploymentId);

    return $deployment && Gate::forUser($user)->allows('view', $deployment->site);
});
