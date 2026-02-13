<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QmhTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'clause',
        'doc_type',
        'version',
        'storage_disk',
        'source_docx_path',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'clause' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(QmhDocumentRevision::class, 'template_id');
    }
}
