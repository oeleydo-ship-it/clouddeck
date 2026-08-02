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

class CheckSitePackagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PACKAGES = ['laravel/horizon', 'laravel/reverb'];

    public int $timeout = 60;

    public function __construct(public readonly string $siteId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);
        $root = '/var/www/'.$site->domain.'/current';
        $checks = collect(self::PACKAGES)->map(fn (string $package) => 'echo '.escapeshellarg($package).'"|$(sudo -u www-data composer show '.escapeshellarg($package).' --format=json 2>/dev/null | jq -r \'.versions[0] // empty\' 2>/dev/null)"')->implode('; ');
        $output = $ssh->run($site->server, 'cd '.escapeshellarg($root).' && '.$checks);

        $installed = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (str_contains($line, '|')) {
                [$package, $version] = explode('|', $line, 2);
                if (trim($version) !== '') {
                    $installed[trim($package)] = trim($version);
                }
            }
        }
        $site->update(['installed_packages' => $installed]);
    }

    public function failed(Throwable $exception): void
    {
        Site::find($this->siteId)?->update(['installed_packages' => null]);
    }
}
