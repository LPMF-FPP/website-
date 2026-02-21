<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhRapatNotulensi extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'rapat_id',
        'version',
        'content',
        'created_by',
        'updated_by',
    ];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(QmhRapat::class, 'rapat_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
