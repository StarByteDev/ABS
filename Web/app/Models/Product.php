<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'category', 'tagline', 'description', 'icon', 'accent', 'features', 'status', 'sort_order', 'is_featured'];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_featured' => 'boolean'];
    }
}
