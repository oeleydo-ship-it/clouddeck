<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ConfigureSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly string $siteId) {}

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);
        $ssh->runScript($site->server, resource_path('scripts/configure-site.sh'), ['DOMAIN' => $site->domain, 'PHP_VERSION' => $site->php_version, 'DOCUMENT_ROOT' => $site->documentRoot()]);
        $site->update(['status' => 'active']);
    }

    public function failed(Throwable $exception): void
    {
        Site::find($this->siteId)?->update(['status' => 'failed']);
    }
}
