<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaVerificationOtp extends Model
{
    use HasFactory;

    protected $table = 'mpesa_verification_otps';

    protected $fillable = [
        'user_id',
        'phone_number',
        'otp_hash',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->verified_at) && $this->expires_at->isFuture() && $this->attempts < 5;
    }
}
