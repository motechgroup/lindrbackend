<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public static function getActiveFaqs()
    {
        return Cache::remember('active_faqs', 3600, function () {
            return self::where('is_active', true)->orderBy('display_order', 'asc')->get();
        });
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('active_faqs');
        });

        static::deleted(function () {
            Cache::forget('active_faqs');
        });
    }
}
