<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelPenalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'moderation_action_id',
        'score_penalty',
        'reason',
    ];

    protected $casts = [
        'score_penalty' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
