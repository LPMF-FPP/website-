<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhRapatPeserta extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'rapat_id',
        'user_id',
        'attendance_status',
        'notes',
    ];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(QmhRapat::class, 'rapat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
