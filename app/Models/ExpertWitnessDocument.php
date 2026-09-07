<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertWitnessDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'expert_witness_request_id', 'document_type', 'disk', 'path',
        'original_filename', 'file_size', 'mime_type',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExpertWitnessRequest::class, 'expert_witness_request_id');
    }
}
