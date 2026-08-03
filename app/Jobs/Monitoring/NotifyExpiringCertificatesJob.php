<?php

namespace App\Jobs\Monitoring;

use App\Models\SslCertificate;
use App\Notifications\OperationalEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A certificate that lapses takes the site down with it, and renewal can fail quietly — DNS
 * moved, port 80 closed behind a firewall. Warning while there is still time to act is the
 * whole point, so this runs daily rather than on the day.
 */
class NotifyExpiringCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $withinDays = 14) {}

    public function handle(): void
    {
        SslCertificate::with('site.user')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($this->withinDays)])
            ->get()
            ->each(function (SslCertificate $certificate): void {
                $user = $certificate->site?->user;
                if (! $user) {
                    return;
                }

                $days = (int) now()->diffInDays($certificate->expires_at, false);

                $user->notify(new OperationalEventNotification(
                    event: 'ssl_expiring',
                    title: 'Certificate for '.$certificate->site->domain.' expires in '.$days.' day'.($days === 1 ? '' : 's'),
                    body: 'The certificate expires on '.$certificate->expires_at->toDayDateTimeString().'. '
                        .($certificate->auto_renew ? 'Automatic renewal is on; check that it has run.' : 'Automatic renewal is off, so it must be renewed by hand.'),
                    url: route('sites.show', $certificate->site).'#ssl',
                    severity: $days <= 3 ? 'critical' : 'warning',
                    context: ['certificate_id' => $certificate->id, 'site_id' => $certificate->site_id, 'days' => $days],
                ));
            });
    }
}
