<?php

use App\Jobs\Backups\DispatchDueBackupsJob;
use App\Jobs\Monitoring\CheckOfflineServersJob;
use App\Jobs\Monitoring\DispatchSiteChecksJob;
use App\Jobs\Monitoring\NotifyExpiringCertificatesJob;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Models\AlertIncident;
use App\Models\DatabaseBackup;
use App\Models\FileOperation;
use App\Models\ServerMetric;
use App\Models\SiteMonitorIncident;
use App\Models\SslCertificate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new CheckOfflineServersJob, 'monitoring')->everyMinute()->withoutOverlapping();
Schedule::job(new DispatchSiteChecksJob, 'monitoring')->everyMinute()->name('dispatch-site-checks')->withoutOverlapping();
Schedule::job(new DispatchDueBackupsJob, 'operations')->everyMinute()->name('dispatch-due-backups')->withoutOverlapping();
Schedule::call(function () {
    ServerMetric::where('recorded_at', '<', now()->subDays(config('monitoring.metric_retention_days')))->delete();
    AlertIncident::where('status', 'resolved')->where('resolved_at', '<', now()->subDays(config('monitoring.incident_retention_days')))->delete();
    SiteMonitorIncident::where('status', 'resolved')->where('resolved_at', '<', now()->subDays(config('monitoring.incident_retention_days')))->delete();
})->dailyAt('03:10')->name('prune-monitoring-history')->withoutOverlapping();
Schedule::call(function () {
    FileOperation::whereNotNull('storage_path')->where('created_at', '<', now()->subHours(config('remote_management.transfer_retention_hours')))->each(function (FileOperation $operation): void {
        Storage::disk($operation->disk)->delete($operation->storage_path);
        $operation->update(['storage_path' => null, 'status' => $operation->status === 'successful' ? 'expired' : $operation->status]);
    });
    DatabaseBackup::where('type', 'export')->where('status', 'ready')->where('created_at', '<', now()->subDays(config('remote_management.database_backup_retention_days')))->each(function (DatabaseBackup $backup): void {
        Storage::disk($backup->disk)->delete($backup->disk_path);
        $backup->update(['disk_path' => null, 'status' => 'expired']);
    });
})->dailyAt('03:30')->name('prune-remote-transfers')->withoutOverlapping();
Schedule::call(function () {
    SslCertificate::where('auto_renew', true)->where('status', 'active')->where('expires_at', '<=', now()->addDays(30))->each(function (SslCertificate $certificate) {
        $certificate->update(['status' => 'pending']);
        InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');
    });
})->dailyAt('02:15')->name('renew-expiring-certificates')->withoutOverlapping();

// Renewal above is automatic but can fail quietly — DNS moved, port 80 closed behind a
// firewall — so the warning goes out on its own schedule rather than depending on it.
Schedule::job(new NotifyExpiringCertificatesJob)
    ->dailyAt('09:00')
    ->name('notify-expiring-certificates')
    ->withoutOverlapping();
