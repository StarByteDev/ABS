<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationLog extends Model
{
    protected $fillable = [
        'dedupe_key', 'channel', 'target', 'type', 'subject', 'title', 'body',
        'data', 'status', 'attempts', 'delivered', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'sent_at' => 'datetime'];
    }
}
