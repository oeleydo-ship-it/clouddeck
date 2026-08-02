<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait BroadcastsQuietly
{
    /**
     * Deployment progress is streamed to the browser over WebSockets so the log view fills
     * in live. That is a convenience, not part of shipping the release: when the Reverb
     * server was unreachable the broadcast threw straight out of the job and a perfectly
     * healthy deployment was recorded as failed on its very first log line. Broadcasting is
     * therefore best effort — the failure is logged and the deployment carries on.
     */
    protected function broadcastQuietly(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            Log::warning('Deployment broadcast failed: '.$e->getMessage());
        }
    }
}
