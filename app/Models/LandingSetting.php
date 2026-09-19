<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LandingSetting extends Model
{
    use HasFactory;

    protected $table = 'landing_settings';

    protected $fillable = [
        'key',
        'value',
        'label',
        'group',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("landing_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, ?string $value, ?string $label = null, string $group = 'general'): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'label' => $label ?? str_replace('_', ' ', ucfirst($key)),
                'group' => $group,
            ]
        );

        Cache::forget("landing_setting_{$key}");

        return $setting;
    }

    public static function getAllSettings(): array
    {
        return Cache::remember('all_landing_settings', 3600, function () {
            return self::pluck('value', 'key')->all();
        });
    }

    public static function clearCache(?string $key = null): void
    {
        Cache::forget('all_landing_settings');
        if ($key) {
            Cache::forget("landing_setting_{$key}");
        }
    }

    protected static function booted(): void
    {
        static::saved(function ($setting) {
            self::clearCache($setting->key);
        });

        static::deleted(function ($setting) {
            self::clearCache($setting->key);
        });
    }
}
