<?php

namespace App\Listeners;

use App\Events\DeploymentFinished;
use App\Notifications\DeploymentFinishedNotification;

class SendDeploymentNotification
{
    public function handle(DeploymentFinished $event): void
    {
        $event->deployment->site->user->notify(new DeploymentFinishedNotification($event->deployment));
    }
}
