<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSession extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_REQUESTED = 'REQUESTED';

    public const STATUS_RESERVED = 'RESERVED';

    public const STATUS_RINGING = 'RINGING';

    public const STATUS_CONNECTED = 'CONNECTED';

    public const STATUS_ENDED = 'ENDED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_REJECTED_BUSY = 'REJECTED_BUSY';

    public const STATUS_REJECTED_INSUFFICIENT_TOKENS = 'REJECTED_INSUFFICIENT_TOKENS';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'caller_id',
        'receiver_id',
        'call_type',
        'room_name',
        'rate_per_minute',
        'status',
        'started_at',
        'connected_at',
        'ended_at',
        'duration_seconds',
        'coins_charged',
        'creator_credits_earned',
        'lindr_share',
        'creator_commission_pct',
        'end_reason',
    ];

    protected $casts = [
        'rate_per_minute' => 'integer',
        'duration_seconds' => 'integer',
        'coins_charged' => 'integer',
        'creator_credits_earned' => 'integer',
        'lindr_share' => 'integer',
        'creator_commission_pct' => 'float',
        'started_at' => 'datetime',
        'connected_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
