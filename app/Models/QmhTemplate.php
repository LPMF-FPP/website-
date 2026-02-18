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
        'shell_mode',
        'orientation_policy',
        'show_signoff_footer',
        'version',
        'storage_disk',
        'is_active',
        'archived_at',
        'metadata',
    ];

    protected $casts = [
        'clause' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'show_signoff_footer' => 'boolean',
        'archived_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(QmhDocumentRevision::class, 'template_id');
    }
}
