<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'female_user_id',
        'credits_deducted',
        'conversion_rate',
        'amount_kes',
        'cash_amount_usd',
        'mpesa_number',
        'method',
        'idempotency_key',
        'status',
        'provider_reference',
        'admin_notes',
        'failure_reason',
        'processed_by_admin_id',
        'processed_at',
    ];

    protected $casts = [
        'credits_deducted' => 'integer',
        'conversion_rate' => 'decimal:4',
        'amount_kes' => 'decimal:2',
        'cash_amount_usd' => 'decimal:2',
        'status' => WithdrawalStatus::class,
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault(function ($user, $withdrawal) {
            return $withdrawal->femaleUser;
        });
    }

    public function femaleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'female_user_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_admin_id');
    }
}
