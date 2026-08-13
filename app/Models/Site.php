<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Events\SiteStatusUpdated;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class Site extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    /**
     * Announced from the model rather than from the jobs that move a site between
     * configuring, active, and failed, so every writer is covered — including any added
     * later, which would otherwise leave the page silently stale again.
     */
    protected static function booted(): void
    {
        static::updated(function (Site $site): void {
            if (! $site->wasChanged('status')) {
                return;
            }

            try {
                SiteStatusUpdated::dispatch($site);
            } catch (Throwable $e) {
                // Configuring a site must not fail because the WebSocket server is down.
                report($e);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'auto_deploy' => 'boolean',
            'zero_downtime' => 'boolean',
            'webhook_secret' => 'encrypted',
            'last_deployed_at' => 'datetime',
            'queue_checked_at' => 'datetime',
            'wordpress_installed_at' => 'datetime',
            'wordpress_checked_at' => 'datetime',
            'wordpress_inventory' => 'array',
            'wordpress_inventory_at' => 'datetime',
            'managed_packages' => 'array',
            'installed_packages' => 'array',
            'horizon_admin_emails' => 'array',
            'site_monitoring_enabled' => 'boolean',
            'monitor_last_checked_at' => 'datetime',
            'dns_last_checked_at' => 'datetime',
        ];
    }

    public function isProduction(): bool
    {
        return ($this->environment ?? 'production') === 'production';
    }

    public function isStaging(): bool
    {
        return ($this->environment ?? 'production') === 'staging';
    }

    public function productionSite()
    {
        return $this->belongsTo(self::class, 'production_site_id');
    }

    public function stagingSites()
    {
        return $this->hasMany(self::class, 'production_site_id');
    }

    public function stagingSite()
    {
        return $this->hasOne(self::class, 'production_site_id');
    }

    /** @return array<int, array<string, mixed>> */
    public function wordpressInventory(string $target): array
    {
        return $this->wordpress_inventory[$target] ?? [];
    }

    public function wordpressIsInstalled(): bool
    {
        return $this->isWordPress() && $this->wordpress_installed_at !== null;
    }

    public function isWordPress(): bool
    {
        return $this->platform === 'wordpress';
    }

    public function isLaravel(): bool
    {
        return ($this->platform ?? 'laravel') === 'laravel';
    }

    public function isReact(): bool
    {
        return $this->platform === 'react';
    }

    public function usesPhp(): bool
    {
        return ! $this->isReact();
    }

    public function platformLabel(): string
    {
        return match (true) {
            $this->isWordPress() => 'WordPress',
            $this->isReact() => 'React',
            default => 'Laravel',
        };
    }

    /**
     * Laravel serves from public/; WordPress from the install root; React/Vite from dist/.
     * Nginx and the deployment both need this, so it lives with the site.
     */
    public function documentRoot(): string
    {
        return match (true) {
            $this->isWordPress() => 'current',
            $this->isReact() => 'current/dist',
            default => 'current/public',
        };
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deployments()
    {
        return $this->hasMany(Deployment::class);
    }

    public function environmentVariables()
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function latestDeployment()
    {
        return $this->hasOne(Deployment::class)->latestOfMany();
    }

    public function sslCertificates()
    {
        return $this->hasMany(SslCertificate::class);
    }

    public function backups()
    {
        return $this->hasMany(SiteBackup::class)->latest();
    }

    public function queueWorkers()
    {
        return $this->hasMany(QueueWorker::class);
    }

    public function cronJobs()
    {
        return $this->hasMany(CronJob::class);
    }

    public function database()
    {
        return $this->hasOne(ManagedDatabase::class);
    }

    public function configurations()
    {
        return $this->hasMany(SiteConfiguration::class);
    }

    public function fileOperations()
    {
        return $this->hasMany(FileOperation::class);
    }

    public function logSnapshots()
    {
        return $this->hasMany(LogSnapshot::class);
    }

    public function terminalCommands()
    {
        return $this->hasMany(TerminalCommand::class);
    }

    public function monitorIncidents()
    {
        return $this->hasMany(SiteMonitorIncident::class)->latest('started_at');
    }

    public function hasActiveSsl(): bool
    {
        return $this->sslCertificates->contains(fn (SslCertificate $certificate) => $certificate->status === 'active')
            || $this->sslCertificates()->where('status', 'active')->exists();
    }

    public function monitorUrl(): string
    {
        $scheme = $this->hasActiveSsl() ? 'https' : 'http';
        $path = $this->monitor_path ?: '/';
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $scheme.'://'.$this->domain.$path;
    }

    public function isDeploying(): bool
    {
        return $this->deployments()
            ->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running])
            ->exists();
    }
}
