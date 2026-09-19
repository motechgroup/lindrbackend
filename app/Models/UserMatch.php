<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'user_low_id',
        'user_high_id',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function userLow(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function userHigh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    /**
     * Get the other user in the match relative to the given user ID.
     */
    public function getOtherUser(int $userId): ?User
    {
        if ($this->user_low_id === $userId) {
            return $this->userHigh;
        }

        if ($this->user_high_id === $userId) {
            return $this->userLow;
        }

        return null;
    }
}
