<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagedDatabase extends Model
{
    use HasUuid, SoftDeletes;

    /** @var list<string> */
    public const ENVIRONMENT_KEYS = [
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Write DB_* environment variables onto the attached site (and clear them from a previous
     * site when this database was the one those keys pointed at).
     */
    public function syncAttachedSiteEnvironment(?Site $previousSite = null): void
    {
        $keys = self::ENVIRONMENT_KEYS;

        if ($previousSite && $previousSite->id !== $this->site_id) {
            $previousSite->environmentVariables()->whereIn('key', $keys)->delete();
        }

        if (! $this->site) {
            return;
        }

        foreach ($this->siteEnvironmentValues() as $key => $value) {
            $this->site->environmentVariables()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'is_secret' => $key === 'DB_PASSWORD'],
            );
        }
    }

    /** @return array<string, string> */
    public function siteEnvironmentValues(): array
    {
        return [
            'DB_CONNECTION' => $this->engine === 'postgresql' ? 'pgsql' : 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $this->engine === 'postgresql' ? '5432' : '3306',
            'DB_DATABASE' => $this->name,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
        ];
    }

    public function backups()
    {
        return $this->hasMany(DatabaseBackup::class);
    }
}
