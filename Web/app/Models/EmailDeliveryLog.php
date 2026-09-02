<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDeliveryLog extends Model
{
    protected $fillable = ['user_id', 'event', 'recipient_email', 'subject', 'status', 'error_message', 'metadata', 'sent_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
