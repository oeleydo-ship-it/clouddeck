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

/**
 * Deploying WordPress only puts the files in place. The install runs afterwards in the
 * browser and creates the tables, so the only honest way to know whether a site is
 * installed is to ask its database.
 */
class CheckWordPressInstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public readonly string $siteId) {}

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);

        if (! $site->isWordPress()) {
            return;
        }

        $environment = $site->environmentVariables()->pluck('value', 'key');
        $database = $environment['DB_DATABASE'] ?? null;

        if (blank($database)) {
            $site->update(['wordpress_installed_at' => null, 'wordpress_checked_at' => now()]);

            return;
        }

        // wp_options carries the siteurl row the installer writes, so its presence is what
        // separates "files deployed" from "install completed".
        $installed = trim($ssh->run($site->server, sprintf(
            'mysql -N -e %s 2>/dev/null || echo 0',
            escapeshellarg(sprintf(
                "select count(*) from information_schema.tables where table_schema=%s and table_name='wp_options'",
                "'".addslashes($database)."'"
            ))
        )));

        $site->update([
            'wordpress_installed_at' => $installed === '1' ? ($site->wordpress_installed_at ?? now()) : null,
            'wordpress_checked_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        // An unreachable server says nothing about whether WordPress is installed, so the
        // last known answer is kept rather than replaced with a guess.
        Site::find($this->siteId)?->update(['wordpress_checked_at' => now()]);
    }
}
