<?php

namespace App\Actions\Sites;

use App\Models\Site;
use App\Services\QuotaManager;
use App\Services\SystemSettings;
use App\Services\WordPressConfig;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Notifications\OperationalEventNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateStagingSite
{
    public function __construct(
        private readonly SystemSettings $settings,
        private readonly QuotaManager $quotas,
    ) {}

    /**
     * @param  array{domain_source: string, staging_slug?: string|null, domain?: string|null, branch?: string|null}  $input
     */
    public function execute(Site $production, array $input): Site
    {
        if (! $this->settings->stagingSitesEnabled()) {
            throw ValidationException::withMessages(['staging' => 'Staging sites are disabled for this platform.']);
        }

        if (! $production->isProduction()) {
            throw ValidationException::withMessages(['staging' => 'Staging can only be created from a production site.']);
        }

        if ($production->status !== 'active') {
            throw ValidationException::withMessages(['staging' => 'The production site must be active before creating staging.']);
        }

        if ($production->stagingSites()->exists()) {
            throw ValidationException::withMessages(['staging' => 'This site already has a staging environment.']);
        }

        $this->quotas->assertCanCreate($production->user, 'sites');

        $source = $input['domain_source'];
        $domain = $this->resolveDomain($production, $source, $input);
        $slug = $source === 'platform' ? Str::lower($input['staging_slug'] ?? '') : null;

        if (Site::query()->where('server_id', $production->server_id)->where('domain', $domain)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['domain' => 'That domain is already used on this server.']);
        }

        if ($source === 'platform' && Site::query()->where('staging_slug', $slug)->where('domain_source', 'platform')->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['staging_slug' => 'That staging subdomain is already taken.']);
        }

        $site = DB::transaction(function () use ($production, $domain, $source, $slug, $input) {
            $site = $production->user->sites()->create([
                'server_id' => $production->server_id,
                'domain' => $domain,
                'platform' => $production->platform,
                'php_version' => $production->php_version,
                'repository_url' => $production->repository_url,
                'branch' => $input['branch'] ?? ($production->branch ?: 'staging'),
                'deployment_script' => $production->deployment_script,
                'auto_deploy' => false,
                'zero_downtime' => $production->zero_downtime ?? true,
                'webhook_secret' => Str::random(64),
                'status' => 'configuring',
                'environment' => 'staging',
                'domain_source' => $source,
                'staging_slug' => $slug,
                'production_site_id' => $production->id,
            ]);

            if ($site->isWordPress()) {
                app(WordPressConfig::class)->ensureSalts($site);
            } else {
                foreach ([
                    'APP_NAME' => $site->domain,
                    'APP_ENV' => 'staging',
                    'APP_DEBUG' => 'true',
                    'APP_URL' => 'https://'.$site->domain,
                    'APP_KEY' => '',
                    'LOG_CHANNEL' => 'stack',
                    'CACHE_STORE' => 'redis',
                    'QUEUE_CONNECTION' => 'redis',
                    'SESSION_DRIVER' => 'redis',
                    'REDIS_HOST' => '127.0.0.1',
                ] as $key => $value) {
                    $site->environmentVariables()->create([
                        'key' => $key,
                        'value' => $value,
                        'is_secret' => $key === 'APP_KEY',
                    ]);
                }
            }

            return $site;
        });

        ConfigureSiteJob::dispatch($site->id)->onQueue('provisioning');

        $production->user->notify(new OperationalEventNotification(
            event: 'site_added',
            title: 'Staging created for '.$production->domain,
            body: $site->domain.' is being configured as a staging site.',
            url: route('sites.show', $site),
            context: ['site_id' => $site->id, 'production_site_id' => $production->id],
        ));

        return $site;
    }

    /**
     * @param  array{staging_slug?: string|null, domain?: string|null}  $input
     */
    private function resolveDomain(Site $production, string $source, array $input): string
    {
        if ($source === 'platform') {
            $slug = Str::lower(trim((string) ($input['staging_slug'] ?? '')));
            if ($slug === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
                throw ValidationException::withMessages(['staging_slug' => 'Enter a valid subdomain slug (letters, numbers, hyphens).']);
            }

            return $slug.'.staging.'.$this->settings->stagingPlatformDomain();
        }

        $domain = Str::lower(trim((string) ($input['domain'] ?? '')));
        if ($domain === '' || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw ValidationException::withMessages(['domain' => 'Enter a valid client staging domain.']);
        }

        if ($domain === $production->domain) {
            throw ValidationException::withMessages(['domain' => 'Staging must use a different domain than production.']);
        }

        return $domain;
    }
}
