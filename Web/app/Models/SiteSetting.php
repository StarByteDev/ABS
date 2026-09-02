<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;
    protected $fillable = ['key', 'value', 'type', 'group'];
}
