<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminBillingRequestController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFeatureFlagController;
use App\Http\Controllers\Admin\AdminImpersonationController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminPostController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\PlatformServicesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImpersonationExitController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CloudAccountController;
use App\Http\Controllers\CloudServerImportController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\CustomServerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ManagedDatabaseController;
use App\Http\Controllers\MonitoringAgentController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PhpExtensionController;
use App\Http\Controllers\PhpMyAdminController;
use App\Http\Controllers\QueueWorkerController;
use App\Http\Controllers\RemoteManagementController;
use App\Http\Controllers\RetryServerProvisioningController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ServerManagementController;
use App\Http\Controllers\ServerOperationController;
use App\Http\Controllers\ServerTeamController;
use App\Http\Controllers\SiteConfigurationController;
use App\Http\Controllers\SiteBackupController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SitePackageController;
use App\Http\Controllers\SshKeyController;
use App\Http\Controllers\SslCertificateController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WordPressController;
use App\Http\Middleware\EnsureDnsEnabled;
use App\Http\Middleware\EnsureManagedServersEnabled;
use App\Http\Middleware\EnsurePublicSiteEnabled;
use App\Http\Middleware\EnsureStagingSitesEnabled;
use App\Http\Controllers\ManagedServerProvisionController;
use App\Http\Controllers\ServerProvisionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'store'])->middleware('throttle:10,1');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
// Every public-facing page sits behind one switch, so turning the marketing site off in
// settings cannot leave one of them reachable by its own URL.
Route::middleware(EnsurePublicSiteEnabled::class)->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/features', [PageController::class, 'features'])->name('features');
    Route::get('/use-cases', [PageController::class, 'useCases'])->name('use-cases');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'submitContact'])->middleware('throttle:5,1')->name('contact.submit');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
Route::post('/webhooks/sites/{site}', WebhookController::class)->middleware('throttle:60,1')->name('webhooks.site');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/impersonation/exit', ImpersonationExitController::class)->middleware(['auth', 'throttle:30,1'])->name('impersonation.exit');
Route::get('/email/verify', fn () => \Inertia\Inertia::render('Auth/VerifyEmail'))->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', fn (EmailVerificationRequest $request) => tap(redirect('/dashboard'), fn () => $request->fulfill()))->middleware(['auth', 'signed'])->name('verification.verify');
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('status', 'Verification link sent.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/requests', [BillingController::class, 'requestPlan'])->name('billing.request');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:10,1')->name('billing.checkout');
    Route::post('/billing/os-backup', [BillingController::class, 'checkoutOsBackup'])->middleware('throttle:10,1')->name('billing.os-backup');
    Route::get('/billing/os-backup/success', [BillingController::class, 'osBackupSuccess'])->name('billing.os-backup.success');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:10,1')->name('billing.portal');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');

    Route::middleware('feature:teams')->group(function () {
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::post('/teams/{team}/invitations', [TeamController::class, 'invite'])->name('teams.invite');
        Route::patch('/teams/{team}/invitations/{invitation}', [TeamController::class, 'updateInvitation'])->name('teams.invitations.update');
        Route::post('/teams/{team}/invitations/{invitation}/resend', [TeamController::class, 'resendInvitation'])->middleware('throttle:10,1')->name('teams.invitations.resend');
        Route::delete('/teams/{team}/invitations/{invitation}', [TeamController::class, 'destroyInvitation'])->name('teams.invitations.destroy');
        Route::get('/team-invitations/{teamInvitation}/{token}', [TeamController::class, 'accept'])->name('team-invitations.accept');
        Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'remove'])->name('teams.members.remove');
        Route::patch('/teams/{team}/members/{member}/role', [TeamController::class, 'role'])->name('teams.members.role');
    });

    Route::get('/docs', DocumentationController::class)->name('docs');
    Route::post('/guide/chat', [GuideController::class, 'chat'])->middleware('throttle:30,1')->name('guide.chat');
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::patch('/account/profile', [AccountController::class, 'profile']);
    Route::put('/account/password', [AccountController::class, 'password']);
    Route::post('/account/two-factor', [AccountController::class, 'enableTwoFactor']);
    Route::post('/account/two-factor/confirm', [AccountController::class, 'confirmTwoFactor']);
    Route::delete('/account/two-factor', [AccountController::class, 'disableTwoFactor']);
    Route::post('/account/tokens', [AccountController::class, 'token']);
    Route::delete('/account/tokens/{token}', [AccountController::class, 'destroyToken']);
    Route::delete('/account/sessions/{session}', [AccountController::class, 'destroySession']);

    Route::middleware('feature:providers')->group(function () {
        Route::get('/cloud-accounts', [CloudAccountController::class, 'index'])->name('cloud-accounts');
        Route::post('/cloud-accounts', [CloudAccountController::class, 'store'])->middleware('throttle:5,1');
        Route::get('/cloud-accounts/{cloudAccount}/servers', [CloudServerImportController::class, 'index'])->name('cloud-accounts.servers');
        Route::post('/cloud-accounts/{cloudAccount}/servers', [CloudServerImportController::class, 'store'])->middleware('throttle:10,1')->name('cloud-accounts.servers.store');
        Route::delete('/cloud-accounts/{cloudAccount}', [CloudAccountController::class, 'destroy']);
    });

    // Behind the platform switch and plan entitlement: hiding the nav entry alone would
    // leave every one of these reachable by anyone who kept a link.
    Route::middleware(['feature:dns', EnsureDnsEnabled::class])->group(function () {
        Route::get('/dns', [DnsController::class, 'index'])->name('dns.index');
        Route::post('/dns/accounts', [DnsController::class, 'store'])->middleware('throttle:5,1')->name('dns.accounts.store');
        Route::post('/dns/accounts/{dnsAccount}/sync', [DnsController::class, 'sync'])->middleware('throttle:20,1')->name('dns.accounts.sync');
        Route::delete('/dns/accounts/{dnsAccount}', [DnsController::class, 'destroy'])->name('dns.accounts.destroy');
        Route::get('/dns/zones/{dnsZone}', [DnsController::class, 'show'])->name('dns.zones.show');
        Route::post('/dns/zones/{dnsZone}/records', [DnsController::class, 'storeRecord'])->name('dns.records.store');
        Route::patch('/dns/zones/{dnsZone}/records/{record}', [DnsController::class, 'updateRecord'])->name('dns.records.update');
        Route::delete('/dns/zones/{dnsZone}/records/{record}', [DnsController::class, 'destroyRecord'])->name('dns.records.destroy');
    });

    Route::middleware('feature:ssh')->group(function () {
        Route::get('/ssh-keys', [SshKeyController::class, 'index'])->name('ssh-keys');
        Route::post('/ssh-keys/generate', [SshKeyController::class, 'generate']);
        Route::post('/ssh-keys', [SshKeyController::class, 'store']);
        Route::get('/ssh-keys/{sshKey}/download', [SshKeyController::class, 'download'])->name('ssh-keys.download');
        Route::delete('/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy']);
    });

    Route::middleware('feature:firewall')->group(function () {
        Route::get('/firewall', [FirewallController::class, 'index'])->name('firewall.index');
        Route::post('/firewall/rules', [FirewallController::class, 'store'])->middleware('throttle:30,1')->name('firewall.rules.store');
        Route::delete('/firewall/rules/{firewallRule}', [FirewallController::class, 'destroy'])->name('firewall.rules.destroy');
        Route::post('/firewall/servers/{server}/sync', [FirewallController::class, 'sync'])->middleware('throttle:20,1')->name('firewall.sync');
        Route::post('/firewall/servers/{server}/refresh', [FirewallController::class, 'refresh'])->middleware('throttle:20,1')->name('firewall.refresh');
    });

    Route::middleware('feature:security')->group(function () {
        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::get('/security/status', [SecurityController::class, 'scanStatus'])->name('security.status');
        Route::post('/security/scan', [SecurityController::class, 'scan'])->middleware('throttle:10,1')->name('security.scan');
        Route::put('/security/settings', [SecurityController::class, 'settings'])->name('security.settings.update');
        Route::delete('/security/settings', [SecurityController::class, 'resetSettings'])->name('security.settings.reset');
        Route::patch('/security/incidents/{securityIncident}/status', [SecurityController::class, 'status'])->name('security.incidents.status');
        Route::post('/security/incidents/{securityIncident}/block', [SecurityController::class, 'block'])->middleware('throttle:10,1')->name('security.incidents.block');
        Route::delete('/security/incidents/{securityIncident}/block', [SecurityController::class, 'unblock'])->middleware('throttle:10,1')->name('security.incidents.unblock');
    });

    Route::post('/notifications/read-all', [NotificationSettingsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationSettingsController::class, 'markRead'])->name('notifications.read');

    Route::middleware('feature:notifications')->group(function () {
        Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::get('/notifications', [NotificationSettingsController::class, 'index'])->name('notifications.index');
        Route::post('/notification-channels', [MonitoringController::class, 'storeChannel'])->name('notification-channels.store');
        Route::delete('/notification-channels/{notificationChannel}', [MonitoringController::class, 'destroyChannel'])->name('notification-channels.destroy');
    });

    Route::get('/servers', [ServerManagementController::class, 'index'])->name('servers.index');
    Route::middleware('feature:providers')->group(function () {
        Route::get('/servers/create', [ServerProvisionController::class, 'create'])->name('servers.create');
        Route::get('/servers/catalog/{cloudAccount}', [ServerProvisionController::class, 'catalog'])->name('servers.catalog');
        Route::post('/servers', [ServerProvisionController::class, 'store'])->middleware('throttle:10,1')->name('servers.store');
    });
    Route::middleware(['feature:managed_servers', EnsureManagedServersEnabled::class])->group(function () {
        Route::get('/servers/managed', [ManagedServerProvisionController::class, 'create'])->name('servers.managed');
        Route::post('/servers/managed', [ManagedServerProvisionController::class, 'store'])->middleware('throttle:10,1')->name('servers.managed.store');
        Route::get('/servers/{server}/managed-checkout/success', [ServerManagementController::class, 'checkoutSuccess'])->name('servers.managed.checkout-success');
        Route::post('/servers/{server}/managed-checkout', [ServerManagementController::class, 'checkout'])->middleware('throttle:10,1')->name('servers.managed.checkout');
    });
    Route::get('/servers/custom', [CustomServerController::class, 'create'])->name('servers.custom');
    Route::post('/servers/custom', [CustomServerController::class, 'store'])->middleware('throttle:10,1')->name('servers.custom.store');
    Route::get('/servers/{server}/manage', [ServerManagementController::class, 'show'])->name('servers.manage');
    Route::delete('/servers/{server}', [ServerManagementController::class, 'destroy'])->name('servers.destroy');
    Route::post('/servers/{server}/retry-provisioning', RetryServerProvisioningController::class)->name('servers.retry-provisioning');
    Route::patch('/servers/{server}/team', [ServerTeamController::class, 'update'])->name('servers.team.update');
    Route::post('/servers/{server}/phpmyadmin', [PhpMyAdminController::class, 'store'])->name('phpmyadmin.store');
    Route::delete('/servers/{server}/phpmyadmin', [PhpMyAdminController::class, 'destroy'])->name('phpmyadmin.destroy');
    Route::post('/servers/{server}/databases', [ManagedDatabaseController::class, 'store'])->name('databases.store');
    Route::patch('/databases/{managedDatabase}', [ManagedDatabaseController::class, 'update'])->name('databases.update');
    Route::delete('/databases/{managedDatabase}', [ManagedDatabaseController::class, 'destroy'])->name('databases.destroy');
    Route::post('/databases/{managedDatabase}/export', [ManagedDatabaseController::class, 'export'])->name('databases.export');
    Route::post('/databases/{managedDatabase}/import', [ManagedDatabaseController::class, 'import'])->name('databases.import');
    Route::get('/database-backups/{databaseBackup}/download', [ManagedDatabaseController::class, 'download'])->name('database-backups.download');
    Route::post('/servers/{server}/cron-jobs', [CronJobController::class, 'store'])->name('cron-jobs.store');
    Route::patch('/cron-jobs/{cronJob}/toggle', [CronJobController::class, 'toggle'])->name('cron-jobs.toggle');
    Route::delete('/cron-jobs/{cronJob}', [CronJobController::class, 'destroy'])->name('cron-jobs.destroy');
    Route::post('/servers/{server}/operations', [ServerOperationController::class, 'store'])->name('server-operations.store');
    Route::post('/servers/{server}/php-extensions', [PhpExtensionController::class, 'store'])->name('php-extensions.store');

    Route::post('/servers/{server}/backup-policies', [BackupController::class, 'store'])->name('backup-policies.store');
    Route::patch('/backup-policies/{backupPolicy}/toggle', [BackupController::class, 'toggle'])->name('backup-policies.toggle');
    Route::post('/backup-policies/{backupPolicy}/run', [BackupController::class, 'run'])->name('backup-policies.run');
    Route::delete('/backup-policies/{backupPolicy}', [BackupController::class, 'destroy'])->name('backup-policies.destroy');

    Route::middleware('feature:database_backups')->group(function () {
        Route::post('/database-backups/{databaseBackup}/restore', [BackupController::class, 'restoreDatabase'])->name('database-backups.restore');
    });

    Route::middleware('feature:os_backups')->group(function () {
        Route::post('/servers/{server}/snapshots', [BackupController::class, 'snapshot'])->name('snapshots.store');
        Route::post('/server-snapshots/{serverSnapshot}/restore', [BackupController::class, 'restoreSnapshot'])->name('snapshots.restore');
        Route::delete('/server-snapshots/{serverSnapshot}', [BackupController::class, 'destroySnapshot'])->name('snapshots.destroy');
    });

    Route::middleware('feature:site_backups')->group(function () {
        Route::post('/sites/{site}/app-backups', [SiteBackupController::class, 'store'])->name('site-backups.store');
        Route::get('/site-backups/{siteBackup}/download', [SiteBackupController::class, 'download'])->name('site-backups.download');
        Route::post('/site-backups/{siteBackup}/full-restore', [SiteBackupController::class, 'restore'])->name('site-backups.restore');
        Route::delete('/site-backups/{siteBackup}', [SiteBackupController::class, 'destroy'])->name('site-backups.destroy');
    });

    Route::middleware('feature:monitoring')->group(function () {
        Route::post('/servers/{server}/monitoring/rotate', [MonitoringController::class, 'rotate'])->name('monitoring.rotate');
        Route::delete('/servers/{server}/monitoring', [MonitoringController::class, 'disable'])->name('monitoring.disable');
        Route::post('/servers/{server}/auto-heal', [MonitoringController::class, 'enableAutoHeal'])->name('auto-heal.enable');
        Route::delete('/servers/{server}/auto-heal', [MonitoringController::class, 'disableAutoHeal'])->name('auto-heal.disable');
        Route::get('/servers/{server}/monitoring/agent', MonitoringAgentController::class)->name('monitoring.agent');
        Route::post('/servers/{server}/alert-rules', [MonitoringController::class, 'storeRule'])->name('alert-rules.store');
        Route::delete('/alert-rules/{alertRule}', [MonitoringController::class, 'destroyRule'])->name('alert-rules.destroy');
    });

    Route::post('/sites/{site}/ssl', [SslCertificateController::class, 'store'])->name('ssl.store');
    Route::post('/sites/{site}/ssl/custom', [SslCertificateController::class, 'storeCustom'])->name('ssl.custom');
    Route::delete('/sites/{site}/ssl', [SslCertificateController::class, 'destroy'])->middleware('throttle:6,1')->name('ssl.destroy');
    Route::post('/sites/{site}/cron-jobs', [CronJobController::class, 'storeForSite'])->name('sites.cron-jobs.store');
    Route::post('/sites/{site}/workers', [QueueWorkerController::class, 'store'])->name('workers.store');
    Route::delete('/workers/{queueWorker}', [QueueWorkerController::class, 'destroy'])->name('workers.destroy');
    Route::post('/workers/{queueWorker}/status', [QueueWorkerController::class, 'status'])->name('workers.status');
    Route::post('/sites/{site}/packages', [SitePackageController::class, 'store'])->name('site-packages.store');
    Route::delete('/sites/{site}/packages', [SitePackageController::class, 'destroy'])->name('site-packages.destroy');
    Route::post('/sites/{site}/packages/check', [SitePackageController::class, 'check'])->name('site-packages.check');
    Route::post('/sites/{site}/horizon-admins', [SitePackageController::class, 'horizonAdmins'])->name('site-horizon-admins.update');
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create', [SiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::middleware(['feature:staging', EnsureStagingSitesEnabled::class])->group(function () {
        Route::post('/sites/{site}/staging', [SiteController::class, 'storeStaging'])->name('sites.staging.store');
        Route::post('/sites/{site}/promote', [SiteController::class, 'promote'])->name('sites.promote');
    });
    Route::middleware('feature:monitoring')->group(function () {
        Route::post('/sites/{site}/monitoring', [SiteController::class, 'enableMonitoring'])->name('sites.monitoring.enable');
        Route::delete('/sites/{site}/monitoring', [SiteController::class, 'disableMonitoring'])->name('sites.monitoring.disable');
        Route::post('/sites/{site}/monitoring/check', [SiteController::class, 'checkMonitoring'])->name('sites.monitoring.check');
    });
    Route::middleware('feature:remote_management')->group(function () {
        Route::get('/sites/{site}/remote', RemoteManagementController::class)->name('sites.remote');
        Route::post('/sites/{site}/configurations', [SiteConfigurationController::class, 'store'])->name('site-configurations.store');
        Route::post('/site-configurations/{siteConfiguration}/rollback', [SiteConfigurationController::class, 'rollback'])->name('site-configurations.rollback');
        Route::post('/sites/{site}/files', [FileManagerController::class, 'store'])->name('site-files.store');
        Route::get('/file-operations/{fileOperation}/download', [FileManagerController::class, 'download'])->name('site-files.download');
        Route::post('/sites/{site}/terminal', [TerminalController::class, 'store'])->middleware('throttle:10,1')->name('terminal.store');
    });
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::put('/sites/{site}/environment', [SiteController::class, 'environment'])->name('sites.environment');
    Route::post('/sites/{site}/deployments', [SiteController::class, 'deploy'])->name('sites.deploy');
    Route::post('/sites/{site}/rollbacks/{deployment}', [SiteController::class, 'rollback'])->name('sites.rollback');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::post('/sites/{site}/queue-health', [SiteController::class, 'queueHealth'])->name('sites.queue-health');
    Route::post('/sites/{site}/wordpress-status', [SiteController::class, 'wordpressStatus'])->name('sites.wordpress-status');
    Route::post('/sites/{site}/wordpress/manage', [WordPressController::class, 'manage'])->name('wordpress.manage');
    Route::post('/sites/{site}/logs', [LogController::class, 'store'])->middleware('throttle:60,1')->name('site-logs.store');
    Route::post('/sites/{site}/wordpress/refresh', [WordPressController::class, 'refresh'])->name('wordpress.refresh');
    Route::post('/sites/{site}/wordpress/backups', [WordPressController::class, 'backup'])->name('wordpress.backup');
    Route::post('/site-backups/{siteBackup}/restore', [WordPressController::class, 'restore'])->name('wordpress.restore');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
    Route::post('/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('deployments.cancel');
    Route::post('/deployments/{deployment}/retry', [DeploymentController::class, 'retry'])->name('deployments.retry');

    Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'overview'])->name('dashboard');
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
        Route::get('/plans', [AdminDashboardController::class, 'plans'])->name('plans');
        Route::get('/feature-flags', [AdminDashboardController::class, 'features'])->name('features');
        Route::get('/billing-review', [AdminDashboardController::class, 'billing'])->name('billing');
        Route::get('/payments', [AdminDashboardController::class, 'payments'])->name('payments');
        Route::get('/storage', [AdminDashboardController::class, 'storage'])->name('storage');
        Route::get('/mail', [AdminDashboardController::class, 'mail'])->name('mail');
        Route::get('/notifications', [AdminDashboardController::class, 'notifications'])->name('notifications');
        Route::get('/settings', [AdminDashboardController::class, 'settings'])->name('settings');
        Route::get('/managed-servers', [AdminDashboardController::class, 'managedServers'])->name('managed-servers');
        Route::get('/pages', [AdminDashboardController::class, 'pages'])->name('pages');
        Route::get('/seo', [AdminDashboardController::class, 'seo'])->name('seo');
        Route::get('/analytics', [AdminDashboardController::class, 'analytics'])->name('analytics');
        Route::get('/webmaster', [AdminDashboardController::class, 'webmaster'])->name('webmaster');
        Route::get('/insert-code', [AdminDashboardController::class, 'insertCode'])->name('insert-code');
        Route::get('/ai', [AdminDashboardController::class, 'ai'])->name('ai');
        Route::get('/google-auth', [AdminDashboardController::class, 'googleAuth'])->name('google-auth');
        Route::get('/platform-services', [PlatformServicesController::class, 'index'])->name('platform-services');
        Route::get('/platform-services/status', [PlatformServicesController::class, 'status'])->name('platform-services.status');
        Route::post('/platform-services/ssl/renew', [PlatformServicesController::class, 'renewSsl'])->name('platform-services.ssl.renew');
        Route::post('/platform-services/{service}/start', [PlatformServicesController::class, 'start'])->name('platform-services.start');
        Route::post('/platform-services/{service}/stop', [PlatformServicesController::class, 'stop'])->name('platform-services.stop');
        Route::post('/platform-services/{service}/restart', [PlatformServicesController::class, 'restart'])->name('platform-services.restart');
        Route::get('/audit', [AdminDashboardController::class, 'audit'])->name('audit');
        Route::get('/posts', [AdminPostController::class, 'index'])->name('posts');
        Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
        Route::post('/posts/ai/suggest-topics', [AdminPostController::class, 'suggestTopics'])->middleware('throttle:10,1')->name('posts.ai.suggest');
        Route::post('/posts/ai/generate', [AdminPostController::class, 'generate'])->middleware('throttle:6,1')->name('posts.ai.generate');
        Route::patch('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
        Route::patch('/posts/{post}/publish', [AdminPostController::class, 'publish'])->name('posts.publish');
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
        Route::get('/users/{user}', [AdminImpersonationController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/impersonate', [AdminImpersonationController::class, 'start'])->middleware('throttle:10,1')->name('users.impersonate');
        Route::patch('/users/{user}/suspension', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'role'])->name('users.role');
        Route::post('/users/{user}/subscription', [AdminUserController::class, 'subscription'])->name('users.subscription');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{plan}/stripe', [AdminPlanController::class, 'stripe'])->name('plans.stripe');
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('plans.destroy');
        Route::post('/feature-flags', [AdminFeatureFlagController::class, 'store'])->name('flags.store');
        Route::patch('/feature-flags/{featureFlag}', [AdminFeatureFlagController::class, 'update'])->name('flags.update');
        Route::patch('/billing-requests/{billingRequest}', [AdminBillingRequestController::class, 'update'])->name('billing-requests.update');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::put('/settings/managed-servers', [AdminSettingController::class, 'managedServers'])->name('settings.managed-servers');
        Route::put('/settings/managed-servers/pricing', [AdminSettingController::class, 'managedServerPricing'])->name('settings.managed-servers.pricing');
        Route::put('/settings/landing', [AdminSettingController::class, 'landing'])->name('settings.landing');
        Route::put('/settings/seo', [AdminSettingController::class, 'seo'])->name('settings.seo');
        Route::put('/settings/analytics', [AdminSettingController::class, 'analytics'])->name('settings.analytics');
        Route::put('/settings/webmaster', [AdminSettingController::class, 'webmaster'])->name('settings.webmaster');
        Route::put('/settings/insert-code', [AdminSettingController::class, 'insertCode'])->name('settings.insert-code');
        Route::put('/settings/ai', [AdminSettingController::class, 'ai'])->name('settings.ai');
        Route::put('/settings/google-auth', [AdminSettingController::class, 'googleAuth'])->name('settings.google-auth');
        Route::put('/settings/stripe', [AdminSettingController::class, 'stripe'])->name('settings.stripe');
        Route::put('/settings/object-storage', [AdminSettingController::class, 'objectStorage'])->name('settings.object-storage');
        Route::post('/settings/object-storage/test', [AdminSettingController::class, 'testObjectStorage'])->middleware('throttle:6,1')->name('settings.object-storage.test');
        Route::put('/settings/os-backup-pricing', [AdminSettingController::class, 'osBackupPricing'])->name('settings.os-backup-pricing');
        Route::put('/settings/branding', [AdminSettingController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/logo', [AdminSettingController::class, 'logo'])->name('settings.logo');
        Route::delete('/settings/logo', [AdminSettingController::class, 'destroyLogo'])->name('settings.logo.destroy');
        Route::put('/settings/mail', [AdminSettingController::class, 'mail'])->name('settings.mail');
        Route::post('/settings/mail/test', [AdminSettingController::class, 'testMail'])->middleware('throttle:6,1')->name('settings.mail.test');
        Route::put('/settings/notifications', [AdminSettingController::class, 'notifications'])->name('settings.notifications');
    });
});
