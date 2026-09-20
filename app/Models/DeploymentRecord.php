<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'initiated_by_user_id',
        'action',
        'branch_version',
        'commit_hash',
        'commit_message',
        'commit_author',
        'executed_migrations',
        'pending_migrations_count',
        'status',
        'output_summary',
        'error_summary',
        'ip_address',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'executed_migrations' => 'array',
        'pending_migrations_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
