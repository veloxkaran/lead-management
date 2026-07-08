<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allCached()->get($key, $default);
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    protected static function allCached()
    {
        return Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key'));
    }
}
