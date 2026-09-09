<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulsePointWallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'lifetime_earned', 'lifetime_spent'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_spent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
