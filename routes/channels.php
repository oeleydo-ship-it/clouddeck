<?php

use App\Models\Deployment;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('deployments.{deploymentId}', function ($user, string $deploymentId) {
    $deployment = Deployment::find($deploymentId);

    return $deployment && Gate::forUser($user)->allows('view', $deployment->site);
});
