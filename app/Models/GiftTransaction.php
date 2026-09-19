<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'sender_id',
        'recipient_id',
        'gift_id',
        'coin_price',
        'platform_share',
        'recipient_share',
        'recipient_earnings_amount',
        'status',
    ];

    protected $casts = [
        'coin_price' => 'integer',
        'platform_share' => 'integer',
        'recipient_share' => 'integer',
        'recipient_earnings_amount' => 'decimal:2',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class);
    }
}
