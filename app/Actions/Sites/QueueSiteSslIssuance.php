<?php

namespace App\Actions\Sites;

use App\Jobs\Operations\InstallSslCertificateJob;
use App\Models\Site;
use App\Models\SslCertificate;
use App\Models\User;

final class QueueSiteSslIssuance
{
    /**
     * Issue Let's Encrypt for a site once its nginx vhost exists. Without HTTPS, Cloudflare
     * and other TLS frontends connect to origin :443 and nginx serves whichever site owns the
     * default SSL block — often the wrong domain entirely.
     */
    public function handle(Site $site, ?User $actor = null): bool
    {
        $site->loadMissing('sslCertificates', 'user');

        $latest = $site->sslCertificates()->latest()->first();

        if ($latest && in_array($latest->status, ['active', 'issuing', 'removing'], true)) {
            return false;
        }

        if ($latest?->provider === 'custom') {
            return false;
        }

        $user = $actor ?? $site->user;
        if (! filled($user?->email)) {
            return false;
        }

        if ($latest && $latest->status === 'pending' && $latest->provider === 'letsencrypt') {
            InstallSslCertificateJob::dispatch($latest->id)->onQueue('operations');

            return true;
        }

        if ($latest && $latest->status === 'failed' && $latest->provider === 'letsencrypt') {
            $latest->update([
                'status' => 'pending',
                'failure_reason' => null,
                'force_https' => true,
                'auto_renew' => true,
            ]);
            InstallSslCertificateJob::dispatch($latest->id)->onQueue('operations');

            return true;
        }

        /** @var SslCertificate $certificate */
        $certificate = $site->sslCertificates()->create([
            'user_id' => $user->id,
            'domains' => [$site->domain],
            'provider' => 'letsencrypt',
            'force_https' => true,
            'auto_renew' => true,
            'status' => 'pending',
        ]);

        InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return true;
    }
}
