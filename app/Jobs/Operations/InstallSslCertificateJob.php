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
use Throwable;

class InstallSslCertificateJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly string $certificateId) {}

    public function handle(SshClient $ssh): void
    {
        $certificate = SslCertificate::with('site.server.sshKey', 'user')->findOrFail($this->certificateId);
        $certificate->update(['status' => 'issuing']);
        $site = $certificate->site;
        // Certbot needs a matching Nginx server_name. Staging/create jobs lost from Redis
        // leave the distro default site answering the hostname, which breaks ACME.
        $ssh->runScript($site->server, resource_path('scripts/configure-site.sh'), [
            'DOMAIN' => $site->domain,
            'PHP_VERSION' => $site->php_version,
            'DOCUMENT_ROOT' => $site->documentRoot(),
            'PLATFORM' => $site->platform ?: 'laravel',
        ]);
        $ssh->runScript($site->server, resource_path('scripts/install-ssl.sh'), ['DOMAIN' => $site->domain, 'EMAIL' => $certificate->user->email, 'REDIRECT' => $certificate->force_https ? 'redirect' : 'no-redirect']);
        $output = $ssh->run($site->server, "openssl x509 -in /etc/letsencrypt/live/{$site->domain}/cert.pem -noout -enddate");
        $expires = trim(str_replace('notAfter=', '', $output));
        $certificate->update(['status' => 'active', 'issued_at' => now(), 'expires_at' => Carbon::parse($expires), 'failure_reason' => null]);

        $site->user?->notify(new OperationalEventNotification(
            event: 'ssl_installed',
            title: 'Certificate issued for '.$site->domain,
            body: 'The certificate is active and expires on '.$certificate->fresh()->expires_at->toFormattedDayDateString().'.',
            url: route('sites.show', ['site' => $site, 'tab' => 'ssl']),
            context: ['certificate_id' => $certificate->id, 'site_id' => $site->id],
        ));

        // A site that gains TLS has to move its WebSocket to wss:// on 443, otherwise the
        // browser blocks the connection as mixed content on the now-HTTPS page.
        if ($site->queueWorkers()->where('type', 'reverb')->exists()) {
            app(ReverbEnvironment::class)->apply($site);
        }
    }

    public function failed(Throwable $e): void
    {
        SslCertificate::find($this->certificateId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
