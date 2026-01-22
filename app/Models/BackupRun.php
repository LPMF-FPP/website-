<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $fillable = [
        'mode',
        'status',
        'started_at',
        'finished_at',
        'triggered_by',
        'artifact_dir',
        'db_dump_path',
        'storage_archive_path',
        'manifest_path',
        'db_size_bytes',
        'storage_size_bytes',
        'git_commit',
        'sha256_manifest',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'db_size_bytes' => 'integer',
        'storage_size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'triggered_by');
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsSuccess(array $data): void
    {
        $this->update(array_merge($data, [
            'status' => 'success',
            'finished_at' => now(),
        ]));
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'finished_at' => now(),
        ]);
    }

    public function getTotalSizeBytes(): int
    {
        return $this->db_size_bytes + $this->storage_size_bytes;
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->getTotalSizeBytes();
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
