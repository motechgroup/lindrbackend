<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevelHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'previous_level',
        'new_level',
        'previous_score',
        'new_score',
        'reason',
        'admin_id',
    ];

    protected $casts = [
        'previous_level' => 'integer',
        'new_level' => 'integer',
        'previous_score' => 'integer',
        'new_score' => 'integer',
        'admin_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
