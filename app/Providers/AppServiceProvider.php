<?php

namespace App\Providers;

use App\Billing\Contracts\BillingGateway;
use App\Billing\ManualBillingGateway;
use App\Events\DeploymentFinished;
use App\Listeners\SendDeploymentNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingGateway::class, ManualBillingGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(DeploymentFinished::class, SendDeploymentNotification::class);
        Gate::define('viewHorizon', fn ($user) => $user->isSuperAdmin());
    }
}
