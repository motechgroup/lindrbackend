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
        'challenge_sequence',
        'status',
        'selfie_path',
        'failure_reason',
        'ip_address',
        'verified_at',
    ];

    protected $casts = [
        'challenge_sequence' => 'array',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
