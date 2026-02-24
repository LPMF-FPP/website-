<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\EmergencyBackupJob;
use App\Models\BackupRun;
use App\Models\JobStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmergencyBackupController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $backupRun = BackupRun::create([
            'mode' => 'emergency',
            'status' => 'queued',
            'triggered_by' => $request->user()->id,
        ]);

        $jobStatus = JobStatus::create([
            'id' => (string) Str::uuid(),
            'type' => 'emergency_backup',
            'status' => 'queued',
        ]);

        EmergencyBackupJob::dispatch($backupRun->id, $jobStatus->id);

        return response()->json([
            'job_id' => $jobStatus->id,
            'backup_run_id' => $backupRun->id,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $backups = BackupRun::query()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (BackupRun $backup) {
                $backup = $this->reconcileArtifactHealth($backup);

                return [
                    'id' => $backup->id,
                    'mode' => $backup->mode,
                    'status' => $backup->status,
                    'created_at' => $backup->created_at?->toIso8601String(),
                    'finished_at' => $backup->finished_at?->toIso8601String(),
                    'triggered_by' => $backup->user?->name,
                    'size' => $backup->getFormattedSize(),
                    'size_bytes' => $backup->getTotalSizeBytes(),
                    'git_commit' => substr($backup->git_commit ?? '', 0, 7),
                    'error_message' => $backup->error_message,
                ];
            });

        return response()->json(['backups' => $backups]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('manage-settings');

        $backup = BackupRun::with('user:id,name')->findOrFail($id);
        $backup = $this->reconcileArtifactHealth($backup);

        return response()->json([
            'id' => $backup->id,
            'mode' => $backup->mode,
            'status' => $backup->status,
            'started_at' => $backup->started_at?->toIso8601String(),
            'finished_at' => $backup->finished_at?->toIso8601String(),
            'triggered_by' => $backup->user?->name,
            'artifact_dir' => $backup->artifact_dir,
            'db_size_bytes' => $backup->db_size_bytes,
            'storage_size_bytes' => $backup->storage_size_bytes,
            'total_size' => $backup->getFormattedSize(),
            'git_commit' => $backup->git_commit,
            'sha256_manifest' => $backup->sha256_manifest,
            'error_message' => $backup->error_message,
            'files' => [
                'db_dump' => $backup->db_dump_path ? basename($backup->db_dump_path) : null,
                'storage_archive' => $backup->storage_archive_path ? basename($backup->storage_archive_path) : null,
                'manifest' => $backup->manifest_path ? basename($backup->manifest_path) : null,
            ],
        ]);
    }

    public function download(int $id, string $file): StreamedResponse
    {
        Gate::authorize('manage-settings');

        $backup = BackupRun::findOrFail($id);
        $backup = $this->reconcileArtifactHealth($backup);

        if ($backup->status !== 'success') {
            abort(422, 'Backup not completed successfully');
        }

        $filePath = match ($file) {
            'db' => $backup->db_dump_path,
            'storage' => $backup->storage_archive_path,
            'manifest' => $backup->manifest_path,
            default => null,
        };

        if (! $filePath || ! file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->streamDownload(function () use ($filePath) {
            readfile($filePath);
        }, basename($filePath));
    }

    private function reconcileArtifactHealth(BackupRun $backup): BackupRun
    {
        if ($backup->status !== 'success') {
            return $backup;
        }

        $missing = $this->detectMissingArtifacts($backup);
        if ($missing === []) {
            return $backup;
        }

        $errorMessage = 'Backup artifact missing: '.implode(', ', $missing);

        $backup->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);

        Log::warning('Emergency backup marked failed due to missing artifacts', [
            'backup_run_id' => $backup->id,
            'missing_artifacts' => $missing,
        ]);

        return $backup->fresh() ?? $backup;
    }

    /**
     * @return array<int,string>
     */
    private function detectMissingArtifacts(BackupRun $backup): array
    {
        $required = [
            'db_dump_path' => 'db',
            'storage_archive_path' => 'storage',
            'manifest_path' => 'manifest',
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            $path = (string) ($backup->{$field} ?? '');
            if ($path === '' || ! file_exists($path)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
