<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivenessVerification extends Model
{
    use HasFactory;

    protected $table = 'liveness_verifications';

    protected $fillable = [
        'user_id',
        'challenge_id',
        'challenge_sequence',
        'gestures_completed',
        'status',
        'selfie_path',
        'failure_reason',
        'ip_address',
        'submitted_at',
        'verified_at',
    ];

    protected $casts = [
        'challenge_sequence' => 'array',
        'gestures_completed' => 'array',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
