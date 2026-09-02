<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinanceConnection extends Model
{
    protected $fillable = [
        'user_id', 'environment', 'label', 'api_key', 'api_secret',
        'is_active', 'permissions', 'last_tested_at', 'last_error',
    ];

    protected $hidden = ['api_key', 'api_secret'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedKey(): string
    {
        $key = (string) $this->api_key;
        return strlen($key) > 8 ? substr($key, 0, 4).'••••'.substr($key, -4) : 'Configured';
    }
}
