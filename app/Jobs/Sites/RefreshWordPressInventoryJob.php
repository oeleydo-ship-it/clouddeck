<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Asks the site what it actually has installed, so the page renders a list rather than the
 * operator reading raw WP-CLI output.
 */
class RefreshWordPressInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly string $siteId) {}

    public function handle(SshClient $ssh): void
    {
        $site = Site::with('server.sshKey')->findOrFail($this->siteId);

        if (! $site->wordpressIsInstalled()) {
            return;
        }

        $inventory = $site->wordpress_inventory ?? [];
        $errors = [];

        foreach (['plugin', 'theme'] as $target) {
            // Each target is read on its own: one of them failing used to abort the job
            // before anything was stored, so a working plugin list was thrown away with it.
            try {
                $output = $ssh->runScript($site->server, resource_path('scripts/wp-cli.sh'), [
                    'DOMAIN' => $site->domain,
                    'ACTION' => 'list',
                    'TARGET' => $target,
                    'SLUG' => '',
                ]);

                $json = strstr($output, '[');
                $decoded = $json === false ? null : json_decode($json, true);

                if (! is_array($decoded)) {
                    throw new RuntimeException('WP-CLI returned no list: '.Str::limit(trim($output), 300));
                }

                $inventory[$target] = $decoded;
            } catch (Throwable $e) {
                report($e);
                $errors[] = Str::limit(trim($e->getMessage()), 300);
            }
        }

        // Stamped even when a target failed, so the page can say what went wrong instead of
        // claiming forever that it is still reading.
        $site->update([
            'wordpress_inventory' => $inventory,
            'wordpress_inventory_at' => now(),
            'wordpress_inventory_error' => $errors === [] ? null : implode(' · ', array_unique($errors)),
        ]);
    }

    public function failed(Throwable $e): void
    {
        // An unreachable server tells us nothing new, so the last known list is kept
        // rather than blanked.
        report($e);

        Site::whereKey($this->siteId)->update([
            'wordpress_inventory_at' => now(),
            'wordpress_inventory_error' => Str::limit(trim($e->getMessage()), 300),
        ]);
    }
}
