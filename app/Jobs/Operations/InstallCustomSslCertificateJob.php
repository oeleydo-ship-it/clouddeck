<?php

namespace App\Jobs\Operations;

use App\Models\SslCertificate;
use App\Notifications\OperationalEventNotification;
use App\Services\ReverbEnvironment;
use App\Ssh\SshClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class InstallCustomSslCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly string $certificateId) {}

    public function handle(SshClient $ssh): void
    {
        $certificate = SslCertificate::with('site.server.sshKey', 'user')->findOrFail($this->certificateId);
        if (! $certificate->isCustom()) {
            throw new RuntimeException('Certificate is not a custom upload.');
        }
        if (! filled($certificate->certificate_pem) || ! filled($certificate->private_key_pem)) {
            throw new RuntimeException('Custom certificate PEMs are missing.');
        }

        $certificate->update(['status' => 'issuing', 'failure_reason' => null]);

        $site = $certificate->site;
        $ssh->runScript($site->server, resource_path('scripts/configure-site.sh'), [
            'DOMAIN' => $site->domain,
            'PHP_VERSION' => $site->php_version,
            'DOCUMENT_ROOT' => $site->documentRoot(),
            'PLATFORM' => $site->platform ?: 'laravel',
        ]);

        $output = $ssh->runScript($certificate->site->server, resource_path('scripts/install-custom-ssl.sh'), [
            'DOMAIN' => $certificate->site->domain,
            'REDIRECT' => $certificate->force_https ? '1' : '0',
            'FULLCHAIN_BASE64' => base64_encode($certificate->certificate_pem),
            'PRIVKEY_BASE64' => base64_encode($certificate->private_key_pem),
        ]);

        $expires = null;
        if (preg_match('/notAfter=(.+)/', $output, $matches) === 1) {
            $expires = Carbon::parse(trim($matches[1]));
        }
        if (! $expires) {
            $remote = $ssh->run(
                $certificate->site->server,
                'openssl x509 -in '.escapeshellarg($certificate->remoteCertificatePath()).' -noout -enddate'
            );
            $expires = Carbon::parse(trim(str_replace('notAfter=', '', $remote)));
        }
        if (! $expires) {
            throw new RuntimeException('Unable to read custom certificate expiry from the server.');
        }

        $certificate->update([
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => $expires,
            'failure_reason' => null,
        ]);

        $certificate->site?->user?->notify(new OperationalEventNotification(
            event: 'ssl_installed',
            title: 'Custom certificate installed for '.$certificate->site->domain,
            body: 'The uploaded certificate is active and expires on '.$certificate->fresh()->expires_at->toFormattedDayDateString().'.',
            url: route('sites.show', ['site' => $certificate->site, 'tab' => 'ssl']),
            context: ['certificate_id' => $certificate->id, 'site_id' => $certificate->site_id, 'provider' => 'custom'],
        ));

        if ($certificate->site->queueWorkers()->where('type', 'reverb')->exists()) {
            app(ReverbEnvironment::class)->apply($certificate->site);
        }
    }

    public function failed(Throwable $e): void
    {
        SslCertificate::find($this->certificateId)?->update([
            'status' => 'failed',
            'failure_reason' => $e->getMessage(),
        ]);
    }
}
