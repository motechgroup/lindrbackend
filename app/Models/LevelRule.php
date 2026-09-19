<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'name',
        'threshold_score',
        'exposure_multiplier',
        'creator_commission_pct',
        'benefits',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'threshold_score' => 'integer',
        'exposure_multiplier' => 'float',
        'creator_commission_pct' => 'float',
        'benefits' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
