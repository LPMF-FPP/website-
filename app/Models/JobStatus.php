<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'status',
        'result',
        'error',
        'progress_current',
        'progress_total',
        'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'completed_at' => 'datetime',
    ];

    public function markAsRunning(): void
    {
        $this->update(['status' => 'running']);
    }

    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $current, int $total): void
    {
        $this->update([
            'progress_current' => $current,
            'progress_total' => $total,
        ]);
    }
}
