<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, $default = null)
    {
        $value = Cache::rememberForever(static::cacheKey($key), function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });

        return $value ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );

        Cache::forget(static::cacheKey($key));
    }

    public static function passingThreshold(): float
    {
        return (float) static::get('passing_threshold', 75);
    }

    protected static function cacheKey(string $key): string
    {
        return 'setting.'.$key;
    }
}
