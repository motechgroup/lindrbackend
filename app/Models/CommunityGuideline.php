<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityGuideline extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'content',
        'status',
        'published_at',
    ];

    protected $casts = [
        'content' => 'array',
        'published_at' => 'datetime',
    ];
}
