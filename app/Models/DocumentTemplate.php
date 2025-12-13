<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'storage_path',
        'meta',
        'updated_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}

