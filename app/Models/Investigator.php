<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Investigator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'rank', 'nrp', 'jurisdiction',
        'phone', 'email', 'address', 'folder_key'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($investigator) {
            if (empty($investigator->folder_key)) {
                $investigator->folder_key = static::generateFolderKey($investigator);
            }
        });

        static::updating(function ($investigator) {
            // Regenerate folder_key if name or nrp changed
            if ($investigator->isDirty(['name', 'nrp'])) {
                $investigator->folder_key = static::generateFolderKey($investigator);
            }
        });
    }

    public function testRequests(): HasMany
    {
        return $this->hasMany(TestRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->rank . ' ' . $this->name;
    }

    /**
     * Generate unique folder key based on NRP and name
     */
    public static function generateFolderKey($investigator): string
    {
        $slug = Str::slug($investigator->name);
        $folderKey = $investigator->nrp ? "{$investigator->nrp}-{$slug}" : $slug;

        // Ensure uniqueness
        $original = $folderKey;
        $counter = 1;
        while (static::where('folder_key', $folderKey)
                    ->where('id', '!=', $investigator->id ?? null)
                    ->exists()) {
            $folderKey = "{$original}-{$counter}";
            $counter++;
        }

        return $folderKey;
    }

    /**
     * Get the investigator's document storage path
     */
    public function getDocumentPath(?string $requestNumber = null, ?string $source = 'uploads', ?string $type = null): string
    {
        $path = "investigators/{$this->folder_key}";
        
        if ($requestNumber) {
            $path .= "/{$requestNumber}";
        }
        
        if ($source) {
            $path .= "/{$source}";
        }
        
        if ($type) {
            $path .= "/{$type}";
        }
        
        return $path;
    }
}
