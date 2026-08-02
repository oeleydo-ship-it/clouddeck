<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateHorizonAdminsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public readonly string $siteId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);
        $root = '/var/www/'.$site->domain.'/current';
        $contents = implode("\n", $site->horizon_admin_emails ?? [])."\n";
        $encoded = base64_encode($contents);
        $ssh->run($site->server, 'cd '.escapeshellarg($root)." && printf '%s' ".escapeshellarg($encoded).' | base64 -d | sudo -u www-data tee storage/app/clouddeck-horizon-admins.txt > /dev/null');
    }
}
