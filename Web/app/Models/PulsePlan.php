<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulsePlan extends Model
{
    public const CAPABILITIES = [
        'scanner' => 'Market scanner',
        'signals' => 'Signals',
        'orders' => 'Orders & positions',
        'trades' => 'Trade history',
        'reports' => 'Reports & performance',
        'binance' => 'Binance API connection',
        'alerts' => 'Alerts',
        'plan_view' => 'Plan & access',
        'settings' => 'Pulse settings',
        'mobile_api' => 'Mobile API',
        'testnet_trading' => 'Practice trading',
        'manual_trading' => 'Manual order execution',
        'live_trading' => 'Live trading',
        'auto_trading' => 'Automatic trading',
    ];

    // Legacy V14 daily scan/signal quota columns may still exist on upgraded
    // databases, but V15.0.3 never uses or serializes them.
    protected $hidden = ['scanner_runs_per_day', 'signals_per_day'];

    protected $fillable = [
        'name', 'slug', 'description', 'monthly_price', 'currency',
        'minimum_signal_score', 'manual_trades_per_day', 'auto_trades_per_day',
        'max_open_trades', 'max_selected_pairs', 'pair_access_mode', 'allow_testnet_trading',
        'allow_manual_trading', 'allow_live_trading', 'allow_auto_trading',
        'allow_mobile_api', 'capabilities', 'is_active', 'sort_order',
        'is_trial', 'is_public', 'request_enabled', 'requires_payment', 'access_days', 'badge', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'minimum_signal_score' => 'decimal:2',
            'allow_testnet_trading' => 'boolean',
            'allow_manual_trading' => 'boolean',
            'allow_live_trading' => 'boolean',
            'allow_auto_trading' => 'boolean',
            'allow_mobile_api' => 'boolean',
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'is_trial' => 'boolean',
            'is_public' => 'boolean',
            'request_enabled' => 'boolean',
            'requires_payment' => 'boolean',
            'access_days' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function pairs(): BelongsToMany
    {
        return $this->belongsToMany(PulsePair::class, 'pulse_plan_pairs')
            ->withPivot(['is_enabled'])
            ->withTimestamps();
    }

    public function strategies(): BelongsToMany
    {
        return $this->belongsToMany(PulseStrategy::class, 'pulse_plan_strategies')
            ->withPivot(['is_enabled', 'weight_override'])
            ->withTimestamps();
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(UserServiceAccess::class, 'pulse_plan_id');
    }

    public function membershipRequests(): HasMany
    {
        return $this->hasMany(PulseMembershipRequest::class, 'pulse_plan_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(PulsePromotionCode::class, 'applicable_plan_id');
    }

    public function scopePubliclyAvailable($query)
    {
        return $query->where('is_active', true)->where('is_public', true)->where('is_trial', false);
    }

    /**
     * Direct USDT package price displayed to customers and captured in the
     * membership-payment request before Admin verification.
     */
    public function effectiveMonthlyPrice(): float
    {
        $stored = max(0.0, (float) $this->monthly_price);
        if ($stored > 0.0 || $this->is_trial) {
            return $stored;
        }

        return match ($this->slug) {
            'pulse-intelligence' => 29.0,
            'pulse-professional' => 79.0,
            default => $stored,
        };
    }


    public function allows(string $capability, bool $default = true): bool
    {
        $capabilities = is_array($this->capabilities) ? $this->capabilities : [];
        if (array_key_exists($capability, $capabilities)) {
            return (bool) $capabilities[$capability];
        }

        return match ($capability) {
            'testnet_trading' => (bool) $this->allow_testnet_trading,
            'manual_trading' => (bool) $this->allow_manual_trading,
            'live_trading' => (bool) $this->allow_live_trading,
            'auto_trading' => (bool) $this->allow_auto_trading,
            'mobile_api' => (bool) $this->allow_mobile_api,
            default => $default,
        };
    }

    public function capabilityMatrix(): array
    {
        $matrix = [];
        foreach (self::CAPABILITIES as $key => $label) {
            $matrix[$key] = [
                'label' => $label,
                'enabled' => $this->allows($key, true),
            ];
        }

        return $matrix;
    }
}
