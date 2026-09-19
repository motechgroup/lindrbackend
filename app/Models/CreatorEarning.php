<?php

namespace App\Models;

use App\Enums\EarningStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorEarning extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'female_user_id',
        'source_type',
        'source_id',
        'gross_coins',
        'recipient_share_percentage',
        'recipient_earnings_amount',
        'status',
        'metadata',
    ];

    protected $casts = [
        'gross_coins' => 'integer',
        'recipient_share_percentage' => 'decimal:2',
        'recipient_earnings_amount' => 'decimal:2',
        'status' => EarningStatus::class,
        'metadata' => 'array',
    ];

    public function femaleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'female_user_id');
    }
}
