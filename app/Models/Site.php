<?php

namespace App\Models;

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
        return ['auto_deploy' => 'boolean', 'zero_downtime' => 'boolean', 'webhook_secret' => 'encrypted', 'last_deployed_at' => 'datetime', 'queue_checked_at' => 'datetime', 'managed_packages' => 'array', 'installed_packages' => 'array', 'horizon_admin_emails' => 'array'];
    }

    public function isWordPress(): bool
    {
        return $this->platform === 'wordpress';
    }

    /**
     * Laravel serves from public/; WordPress serves from the install root. Nginx and the
     * deployment both need this, so it lives with the site rather than in each of them.
     */
    public function documentRoot(): string
    {
        return $this->isWordPress() ? 'current' : 'current/public';
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

    public function terminalCommands()
    {
        return $this->hasMany(TerminalCommand::class);
    }
}
