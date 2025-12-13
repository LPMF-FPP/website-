<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'investigator_id', 'test_request_id', 'document_type', 
        'source', 'filename', 'original_filename', 'file_path', 'path',
        'file_size', 'mime_type', 'generated_by', 'extra'
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'extra' => 'array',
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
