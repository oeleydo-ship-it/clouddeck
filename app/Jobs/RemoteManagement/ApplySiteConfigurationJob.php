<?php

namespace App\Jobs\RemoteManagement;

use App\Models\SiteConfiguration;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ApplySiteConfigurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly string $configurationId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $configuration = SiteConfiguration::with(['site.server.sshKey', 'site.sslCertificates'])->findOrFail($this->configurationId);
        $configuration->update(['status' => 'applying', 'failure_reason' => null]);
        $site = $configuration->site;
        $settings = $configuration->settings;

        if ($configuration->type === 'nginx') {
            $activeCertificate = $site->sslCertificates->firstWhere('status', 'active');
            if ($settings['include_www'] && $activeCertificate && ! in_array('www.'.$site->domain, $activeCertificate->domains, true)) {
                throw new \RuntimeException('Issue a certificate containing the www hostname before enabling it in Nginx.');
            }
            $ssh->runScript($site->server, resource_path('scripts/apply-nginx-settings.sh'), [
                'DOMAIN' => $site->domain,
                'PHP_VERSION' => $site->php_version,
                'CLIENT_MAX_BODY_MB' => $settings['client_max_body_mb'],
                'STATIC_CACHE' => ! empty($settings['static_cache']) ? '1' : '0',
                'INCLUDE_WWW' => ! empty($settings['include_www']) ? '1' : '0',
                'ALLOW_IFRAME_EMBEDDING' => ! empty($settings['allow_iframe_embedding']) ? '1' : '0',
                'SSL_ENABLED' => $activeCertificate ? '1' : '0',
                'SSL_CERTIFICATE' => $activeCertificate?->remoteCertificatePath() ?? '',
                'SSL_CERTIFICATE_KEY' => $activeCertificate?->remotePrivateKeyPath() ?? '',
                'DOCUMENT_ROOT' => $site->documentRoot(),
            ]);
        } else {
            $ssh->runScript($site->server, resource_path('scripts/apply-php-settings.sh'), [
                'DOMAIN' => $site->domain,
                'PHP_VERSION' => $site->php_version,
                'MEMORY_LIMIT_MB' => $settings['memory_limit_mb'],
                'UPLOAD_MAX_MB' => $settings['upload_max_mb'],
                'POST_MAX_MB' => $settings['post_max_mb'],
                'MAX_EXECUTION_TIME' => $settings['max_execution_time'],
                'MAX_CHILDREN' => $settings['max_children'],
                'DISPLAY_ERRORS' => $settings['display_errors'] ? 'on' : 'off',
            ]);
        }

        $configuration->update(['status' => 'active', 'applied_at' => now()]);
        SiteConfiguration::where('site_id', $site->id)->where('type', $configuration->type)->where('id', '!=', $configuration->id)->where('status', 'active')->update(['status' => 'superseded']);
    }

    public function failed(Throwable $exception): void
    {
        SiteConfiguration::find($this->configurationId)?->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
    }
}
