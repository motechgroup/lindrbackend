<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_url',
        'animation_reference',
        'coin_price',
        'recipient_share_percentage',
        'is_active',
    ];

    protected $casts = [
        'coin_price' => 'integer',
        'recipient_share_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class);
    }
}
