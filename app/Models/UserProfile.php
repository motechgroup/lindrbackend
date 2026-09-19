<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'date_of_birth',
        'gender',
        'bio',
        'city',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'interests',
        'online_status',
        'last_heartbeat_at',
        'current_call_session_id',
        'profile_visibility',
        'is_verified',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'last_heartbeat_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'interests' => 'array',
        'is_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interests()
    {
        return $this->user ? $this->user->interests() : null;
    }
}
