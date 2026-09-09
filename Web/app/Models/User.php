<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'email_verified_at', 'country_code', 'phone', 'country', 'password', 'role', 'status', 'private_member_approved_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'private_member_approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPrivateMember(): bool
    {
        return $this->role === 'private_member' && $this->private_member_approved_at !== null && $this->status === 'active';
    }

    public function portfolioAccount(): HasOne
    {
        return $this->hasOne(PortfolioAccount::class);
    }

    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function serviceAccesses(): HasMany
    {
        return $this->hasMany(UserServiceAccess::class);
    }

    public function pulseAccess(): HasOne
    {
        return $this->hasOne(UserServiceAccess::class)->where('service', 'pulse');
    }

    public function pulseSettings(): HasOne
    {
        return $this->hasOne(PulseUserSetting::class);
    }

    public function binanceConnections(): HasMany
    {
        return $this->hasMany(BinanceConnection::class);
    }

    public function pulseSignals(): HasMany
    {
        return $this->hasMany(PulseSignal::class);
    }

    public function pulseAutomationRuns(): HasMany
    {
        return $this->hasMany(PulseAutomationRun::class);
    }

    public function pulseTrades(): HasMany
    {
        return $this->hasMany(PulseTrade::class);
    }

    public function pulseAlerts(): HasMany
    {
        return $this->hasMany(PulseAlert::class);
    }

    public function pulseMembershipRequests(): HasMany
    {
        return $this->hasMany(PulseMembershipRequest::class);
    }

    public function pulsePromotionRedemptions(): HasMany
    {
        return $this->hasMany(PulsePromotionRedemption::class);
    }

    public function mobileDevices(): HasMany
    {
        return $this->hasMany(MobileDevice::class);
    }





    public function pulseMissionProgress(): HasMany
    {
        return $this->hasMany(PulseUserMission::class);
    }


    public function emailDeliveryLogs(): HasMany
    {
        return $this->hasMany(EmailDeliveryLog::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function hasPulseAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $access = $this->relationLoaded('pulseAccess')
            ? $this->getRelation('pulseAccess')
            : $this->pulseAccess()->with('plan')->first();

        return $access instanceof UserServiceAccess && $access->isActive();
    }

    public function pulsePlan(): ?PulsePlan
    {
        $access = $this->relationLoaded('pulseAccess')
            ? $this->getRelation('pulseAccess')
            : $this->pulseAccess()->with('plan')->first();

        return $access?->plan;
    }

}
