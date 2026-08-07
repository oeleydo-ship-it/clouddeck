<?php

use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CloudAccountValidationController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\ManagedDatabaseController;
use App\Http\Controllers\Api\MetricController;
use App\Http\Controllers\Api\MetricIngestionController;
use App\Http\Controllers\Api\SecurityEventIngestionController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SslCertificateController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('monitoring/{server}/metrics', MetricIngestionController::class)->middleware('throttle:120,1');
Route::post('monitoring/{server}/security-events', SecurityEventIngestionController::class)->middleware('throttle:120,1');
Route::post('billing/stripe/webhook', StripeWebhookController::class)->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('cloud-accounts/validate', CloudAccountValidationController::class)->middleware(['abilities:servers:write', 'throttle:5,1']);
    // Named with an "api." prefix: the web routes use the plain "servers.*" names for the
    // same resource, and a same-named web/API route pair silently makes route() resolve to
    // whichever was registered last — e.g. web forms posting to this JSON API by accident.
    Route::get('servers', [ServerController::class, 'index'])->middleware('abilities:servers:read')->name('api.servers.index');
    Route::get('servers/{server}', [ServerController::class, 'show'])->middleware('abilities:servers:read')->name('api.servers.show');
    Route::post('servers', [ServerController::class, 'store'])->middleware('abilities:servers:write')->name('api.servers.store');
    Route::delete('servers/{server}', [ServerController::class, 'destroy'])->middleware('abilities:servers:write')->name('api.servers.destroy');
    Route::post('servers/{server}/actions', [ServerController::class, 'action'])->middleware(['abilities:servers:write', 'throttle:10,1']);
    Route::get('sites', [SiteController::class, 'index'])->middleware('abilities:servers:read');
    Route::get('sites/{site}', [SiteController::class, 'show'])->middleware('abilities:servers:read');
    Route::post('sites', [SiteController::class, 'store'])->middleware('abilities:servers:write');
    Route::post('sites/{site}/deployments', [SiteController::class, 'deploy'])->middleware('abilities:servers:write');
    Route::get('deployments', [DeploymentController::class, 'index'])->middleware('abilities:servers:read');
    Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->middleware('abilities:servers:read');
    Route::get('databases', [ManagedDatabaseController::class, 'index'])->middleware('abilities:servers:read');
    Route::post('databases', [ManagedDatabaseController::class, 'store'])->middleware('abilities:servers:write');
    Route::delete('databases/{managedDatabase}', [ManagedDatabaseController::class, 'destroy'])->middleware('abilities:servers:write');
    Route::get('ssl', [SslCertificateController::class, 'index'])->middleware('abilities:servers:read');
    Route::post('ssl', [SslCertificateController::class, 'store'])->middleware('abilities:servers:write');
    Route::get('metrics', [MetricController::class, 'index'])->middleware('abilities:servers:read');
    Route::get('backups', [BackupController::class, 'index'])->middleware('abilities:servers:read');
    Route::post('servers/{server}/backup-policies', [BackupController::class, 'store'])->middleware('abilities:servers:write');
    Route::post('backup-policies/{backupPolicy}/run', [BackupController::class, 'run'])->middleware('abilities:servers:write');
    Route::delete('backup-policies/{backupPolicy}', [BackupController::class, 'destroy'])->middleware('abilities:servers:write');
    Route::get('profile', fn (Request $request) => $request->user());
});
