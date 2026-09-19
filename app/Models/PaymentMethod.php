<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'provider_code',
        'enabled',
        'supported_countries',
        'supported_currencies',
        'minimum_amount',
        'maximum_amount',
        'display_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'supported_countries' => 'array',
        'supported_currencies' => 'array',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'display_order' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_code', 'code');
    }
}
