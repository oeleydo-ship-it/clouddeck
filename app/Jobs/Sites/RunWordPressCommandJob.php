<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Models\TerminalCommand;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Plugin and theme management runs through WP-CLI rather than by unpacking archives,
 * because activating either writes to the database — a file dropped into wp-content is
 * present but inert.
 */
class RunWordPressCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $commandId,
        public readonly string $action,
        public readonly string $target,
        public readonly string $slug = '',
    ) {}

    public function handle(SshClient $ssh): void
    {
        $command = TerminalCommand::with('site.server.sshKey')->findOrFail($this->commandId);
        $command->update(['status' => 'running', 'started_at' => now()]);

        $output = $ssh->runScript($command->site->server, resource_path('scripts/wp-cli.sh'), [
            'DOMAIN' => $command->site->domain,
            'ACTION' => $this->action,
            'TARGET' => $this->target,
            'SLUG' => $this->slug,
        ]);

        $command->update(['status' => 'successful', 'output' => $output, 'exit_code' => 0, 'finished_at' => now()]);
        $this->recordInventory($command->site, $output);

        // Anything that installs, removes, or toggles has changed the list, so the stored
        // inventory is refreshed rather than left describing the site as it used to be.
        if ($this->action !== 'list') {
            RefreshWordPressInventoryJob::dispatch($command->site_id)->onQueue('operations');
        }
    }

    private function recordInventory(Site $site, string $output): void
    {
        if ($this->action !== 'list') {
            return;
        }

        // WP-CLI prints warnings before its JSON, so the payload is taken from the first
        // bracket rather than assuming the whole output parses.
        $json = strstr($output, '[');
        $decoded = $json === false ? null : json_decode($json, true);

        if (! is_array($decoded)) {
            return;
        }

        $site->update([
            'wordpress_inventory' => [...($site->wordpress_inventory ?? []), $this->target => $decoded],
            'wordpress_inventory_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        TerminalCommand::find($this->commandId)?->update(['status' => 'failed', 'output' => $exception->getMessage(), 'exit_code' => 1, 'finished_at' => now()]);
    }
}
