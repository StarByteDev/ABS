<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = [
        'support_conversation_id', 'user_id', 'sender_type', 'message_type', 'body', 'metadata',
        'read_by_customer_at', 'read_by_admin_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_by_customer_at' => 'datetime',
            'read_by_admin_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
