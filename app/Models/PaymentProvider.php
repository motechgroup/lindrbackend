<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'enabled',
        'test_mode',
        'priority',
        'supported_countries',
        'supported_currencies',
        'configuration',
        'webhook_secret',
        'status',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'test_mode' => 'boolean',
        'priority' => 'integer',
        'supported_countries' => 'array',
        'supported_currencies' => 'array',
        'configuration' => 'encrypted:array',
        'webhook_secret' => 'encrypted',
    ];

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'provider_code', 'code');
    }
}
