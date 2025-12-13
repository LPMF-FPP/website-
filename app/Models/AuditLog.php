<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'action',
        'target',
        'before',
        'after',
        'context',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'context' => 'array',
    ];
}

