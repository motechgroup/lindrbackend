<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinPurchase extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'package_id',
        'amount_kes',
        'coins_credited',
        'payment_method',
        'status',
        'phone_number',
        'checkout_request_id',
        'merchant_request_id',
        'mpesa_receipt_number',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_kes' => 'decimal:2',
        'coins_credited' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CoinPackage::class, 'package_id');
    }
}
