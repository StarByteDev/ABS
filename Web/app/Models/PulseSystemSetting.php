<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PulseSystemSetting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json' => json_decode((string) $setting->value, true) ?: $default,
            default => $setting->value,
        };
    }
}
