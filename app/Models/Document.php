<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'investigator_id', 'test_request_id', 'document_type', 
        'source', 'filename', 'original_filename', 'file_path', 'path',
        'file_size', 'mime_type', 'generated_by', 'extra', 'storage_disk',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'extra' => 'array',
        'file_size' => 'integer',
    ];

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
