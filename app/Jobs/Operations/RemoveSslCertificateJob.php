<?php

namespace App\Jobs\Operations;

use App\Models\SslCertificate;
use App\Notifications\OperationalEventNotification;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RemoveSslCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly string $certificateId) {}

    public function handle(SshClient $ssh): void
    {
        $certificate = SslCertificate::with('site.server.sshKey', 'user')->findOrFail($this->certificateId);
        $site = $certificate->site;

        $certificate->update(['status' => 'removing', 'failure_reason' => null]);

        $ssh->runScript($site->server, resource_path('scripts/remove-ssl.sh'), [
            'DOMAIN' => $site->domain,
            'PHP_VERSION' => $site->php_version,
            'DOCUMENT_ROOT' => $site->documentRoot(),
            'PLATFORM' => $site->platform ?: 'laravel',
        ]);

        $domain = $site->domain;
        $certificate->delete();

        $site->user?->notify(new OperationalEventNotification(
            event: 'ssl_removed',
            title: 'SSL removed for '.$domain,
            body: 'The certificate was uninstalled and the site is serving HTTP. Issue Let’s Encrypt or upload a custom certificate when ready.',
            url: route('sites.show', ['site' => $site, 'tab' => 'ssl']),
            context: ['site_id' => $site->id],
        ));
    }

    public function failed(Throwable $e): void
    {
        SslCertificate::find($this->certificateId)?->update([
            'status' => 'failed',
            'failure_reason' => $e->getMessage(),
        ]);
    }
}
