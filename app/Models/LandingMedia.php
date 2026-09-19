<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class LandingMedia extends Model
{
    use HasFactory;

    protected $table = 'landing_media';

    protected $fillable = [
        'key',
        'title',
        'description',
        'file_path',
        'alt_text',
        'section',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function getMedia(string $key): ?self
    {
        return Cache::remember("landing_media_{$key}", 3600, function () use ($key) {
            return self::where('key', $key)->where('is_active', true)->first();
        });
    }

    public static function getAllMedia(): array
    {
        return Cache::remember('all_landing_media', 3600, function () {
            return self::where('is_active', true)
                ->get()
                ->mapWithKeys(fn (self $media) => [$media->key => $media->url])
                ->all();
        });
    }

    public static function clearCache(?string $key = null): void
    {
        Cache::forget('all_landing_media');
        if ($key) {
            Cache::forget("landing_media_{$key}");
        }
    }

    public function getUrlAttribute(): string
    {
        if (empty($this->file_path)) {
            return asset('images/defaults/placeholder.png');
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    protected static function booted(): void
    {
        static::saved(function ($media) {
            self::clearCache($media->key);
        });

        static::deleted(function ($media) {
            self::clearCache($media->key);
        });
    }
}
