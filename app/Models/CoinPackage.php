<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoinPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'coin_amount',
        'bonus_coins',
        'price_kes',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'coin_amount' => 'integer',
        'bonus_coins' => 'integer',
        'price_kes' => 'decimal:2',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function getTotalCoinsAttribute(): int
    {
        return $this->coin_amount + $this->bonus_coins;
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CoinPurchase::class, 'package_id');
    }
}
