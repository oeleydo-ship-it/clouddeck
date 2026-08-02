<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly string $siteId) {}

    public function handle(SshClient $ssh): void
    {
        $site = Site::withTrashed()->with('server.sshKey')->findOrFail($this->siteId);
        $ssh->runScript($site->server, resource_path('scripts/remove-site.sh'), ['DOMAIN' => $site->domain, 'PHP_VERSION' => $site->php_version]);
    }
}
