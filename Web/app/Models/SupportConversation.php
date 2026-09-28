<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $fillable = [
        'user_id', 'assigned_admin_id', 'name', 'email', 'subject', 'category', 'channel',
        'status', 'priority', 'last_message_at', 'last_customer_message_at',
        'last_admin_message_at', 'assistant_handled_at', 'escalated_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'last_admin_message_at' => 'datetime',
            'assistant_handled_at' => 'datetime',
            'escalated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed'], true);
    }
}
