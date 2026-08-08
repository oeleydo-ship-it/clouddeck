<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SslCertificate extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'auto_renew' => 'boolean',
            'force_https' => 'boolean',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'certificate_pem' => 'encrypted',
            'private_key_pem' => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isCustom(): bool
    {
        return ($this->provider ?? 'letsencrypt') === 'custom';
    }

    /**
     * Absolute path to the fullchain PEM on the managed host.
     */
    public function remoteCertificatePath(): string
    {
        $domain = $this->site?->domain ?? '';

        return $this->isCustom()
            ? "/etc/ssl/clouddeck/{$domain}/fullchain.pem"
            : "/etc/letsencrypt/live/{$domain}/fullchain.pem";
    }

    /**
     * Absolute path to the private key PEM on the managed host.
     */
    public function remotePrivateKeyPath(): string
    {
        $domain = $this->site?->domain ?? '';

        return $this->isCustom()
            ? "/etc/ssl/clouddeck/{$domain}/privkey.pem"
            : "/etc/letsencrypt/live/{$domain}/privkey.pem";
    }
}
