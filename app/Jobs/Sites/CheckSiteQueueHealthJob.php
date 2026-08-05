<?php

namespace App\Jobs\Sites;

use App\Models\Site;
use App\Notifications\OperationalEventNotification;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CheckSiteQueueHealthJob implements ShouldQueue
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
        $output = $ssh->run($site->server, 'cd '.escapeshellarg($root)." && sudo -u www-data php artisan tinker --execute=\"echo DB::table('failed_jobs')->count();\" 2>&1");
        preg_match('/(\d+)\s*$/', trim($output), $match);
        $previous = (int) $site->queue_failed_count;
        $current = isset($match[1]) ? (int) $match[1] : null;
        $site->update(['queue_failed_count' => $current, 'queue_checked_at' => now()]);

        // Only when the count has grown. Repeating every hour that the same twelve jobs are
        // still failing teaches the reader to ignore the channel.
        if ($current !== null && $current > $previous) {
            $site->user?->notify(new OperationalEventNotification(
                event: 'queue_failed',
                title: 'Failed jobs on '.$site->domain,
                body: $current.' job'.($current === 1 ? '' : 's').' in the failed table, up from '.$previous.'.',
                url: route('sites.show', ['site' => $site, 'tab' => 'queue']),
                context: ['site_id' => $site->id, 'failed' => $current, 'previous' => $previous],
            ));
        }
    }

    public function failed(Throwable $exception): void
    {
        Site::find($this->siteId)?->update(['queue_failed_count' => null, 'queue_checked_at' => now()]);
    }
}
