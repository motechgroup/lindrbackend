<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'admin_id',
        'target_user_id',
        'report_id',
        'action',
        'reason',
        'score_penalty',
        'previous_level',
        'new_level',
        'previous_status',
        'new_status',
        'notes',
    ];

    protected $casts = [
        'score_penalty' => 'integer',
        'previous_level' => 'integer',
        'new_level' => 'integer',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(UserReport::class, 'report_id');
    }
}
