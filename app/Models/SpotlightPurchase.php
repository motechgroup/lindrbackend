<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotlightPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'tokens_spent',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'tokens_spent' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpotlightPackage::class, 'package_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
