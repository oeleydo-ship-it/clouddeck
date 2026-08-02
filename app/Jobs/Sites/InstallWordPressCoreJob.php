<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

/**
 * Runs WordPress's own setup, which the browser wizard would otherwise have to do by hand.
 * Deploying the files leaves a site that looks finished but has no tables and no way in, so
 * this is what turns a deployment into a working site.
 */
class InstallWordPressCoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $siteId,
        public readonly string $title,
        public readonly string $adminUser,
        public readonly string $adminEmail,
        #[SensitiveParameter] public readonly string $adminPassword,
    ) {}

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);

        $ssh->runScript($site->server, resource_path('scripts/wp-core-install.sh'), [
            'DOMAIN' => $site->domain,
            'SITE_URL' => 'https://'.$site->domain,
            'TITLE' => $this->title,
            'ADMIN_USER' => $this->adminUser,
            'ADMIN_EMAIL' => $this->adminEmail,
            'ADMIN_PASSWORD' => $this->adminPassword,
        ]);

        // Whether it worked is decided by looking at the database, not by trusting this.
        CheckWordPressInstallJob::dispatch($site->id)->onQueue('operations');
    }
}
