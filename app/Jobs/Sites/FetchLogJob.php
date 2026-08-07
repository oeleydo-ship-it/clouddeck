<?php

namespace App\Jobs\Sites;

use App\Models\LogSnapshot;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads a log off the server so it can be looked at without an SSH session. The source is a
 * name from a fixed list, never a path, so this can only ever read the logs Uplary knows
 * about.
 */
class FetchLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public readonly string $snapshotId) {}

    public function handle(SshClient $ssh): void
    {
        $snapshot = LogSnapshot::with(['server.sshKey', 'site'])->findOrFail($this->snapshotId);
        $snapshot->update(['status' => 'running']);

        $output = $ssh->runScript($snapshot->server, resource_path('scripts/read-log.sh'), [
            'DOMAIN' => $snapshot->site?->domain ?? '',
            'SOURCE' => $snapshot->source,
            'LINES' => (string) $snapshot->lines,
            'PHP_VERSION' => $snapshot->site?->php_version ?? config('clouddeck.default_php_version'),
        ]);

        $path = null;
        if (preg_match('/CLOUDDECK_LOG_PATH=(\S+)/', $output, $match)) {
            $path = $match[1] === 'none' ? null : $match[1];
            $output = trim(str_replace($match[0], '', $output));
        }

        $snapshot->update([
            'status' => 'completed',
            'path' => $path,
            // The column is mediumtext; a log line can be enormous and one runaway entry
            // should not fail the write.
            'output' => Str::limit($output, 900000, ''),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        LogSnapshot::whereKey($this->snapshotId)->update([
            'status' => 'failed',
            'output' => Str::limit($exception->getMessage(), 5000, ''),
        ]);
    }
}
