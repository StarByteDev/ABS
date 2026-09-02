<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'slug', 'summary', 'body', 'category', 'asset_symbol', 'risk_level', 'image_url', 'status', 'is_featured', 'published_at'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'published_at' => 'datetime'];
    }
}
