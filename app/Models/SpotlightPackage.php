<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpotlightPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_minutes',
        'token_cost',
        'boost_multiplier',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'token_cost' => 'integer',
        'boost_multiplier' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
