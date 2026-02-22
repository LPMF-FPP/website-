<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhRapatAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rapat_id',
        'file_disk',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(QmhRapat::class, 'rapat_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return in_array((string) $this->file_mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }
}
