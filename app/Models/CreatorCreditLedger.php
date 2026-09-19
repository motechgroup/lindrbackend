<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorCreditLedger extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'creator_credit_ledgers';

    protected $fillable = [
        'user_id',
        'transaction_type',
        'amount_credits',
        'balance_after',
        'conversion_rate',
        'cash_value_kes',
        'cash_value_usd',
        'gross_token_amount',
        'platform_share_tokens',
        'description',
        'reference_id',
        'reference_type',
        'created_at',
    ];

    protected $casts = [
        'amount_credits' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'cash_value_kes' => 'decimal:2',
        'cash_value_usd' => 'decimal:2',
        'gross_token_amount' => 'integer',
        'platform_share_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
