<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'preferences', 'status', 'confirmed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['preferences' => 'array', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
