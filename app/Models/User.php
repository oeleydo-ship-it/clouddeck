<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'timezone',
        'current_team_id',
        'stripe_customer_id',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function cloudAccounts()
    {
        return $this->hasMany(CloudAccount::class);
    }

    public function sshKeys()
    {
        return $this->hasMany(SshKey::class);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }

    public function notificationChannels()
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function billingRequests()
    {
        return $this->hasMany(BillingRequest::class);
    }

    public function billingInvoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function currentSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function teamMemberships()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function accessibleServers()
    {
        return Server::query()->accessibleTo($this);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
