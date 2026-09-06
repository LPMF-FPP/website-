<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\GowaUpdateOperation;
use RuntimeException;

final class GowaUpdateReconciler
{
    public function __construct(
        private readonly GowaUpdateService $service,
        private readonly GowaEvidenceImporter $evidence,
    ) {}

    public function reconcile(): int
    {
        $this->importEvidence();
        $this->cleanupOrphanedRequests();
        $count = 0;
        GowaUpdateOperation::query()->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)
            ->where('lease_expires_at', '<', now())->each(function (GowaUpdateOperation $operation) use (&$count): void {
                $this->service->reconcileStale($operation);
                $count++;
            });

        return $count;
    }

    private function cleanupOrphanedRequests(): void
    {
        $root = (string) config('gowa-updater.request_root', '');
        if ($root === '' || ! is_dir($root) || is_link($root)) {
            return;
        }

        GowaUpdateOperation::query()
            ->where('scope', GowaUpdateOperation::SCOPE)
            ->whereIn('status', GowaUpdateOperation::TERMINAL_STATUSES)
            ->latest('updated_at')
            ->limit(256)
            ->pluck('id')
            ->each(function (string $operationId) use ($root): void {
                $pending = $root.'/'.$operationId.'/request.pending';
                if (is_file($pending) && ! is_link($pending)) {
                    @unlink($pending);
                }
            });
    }

    private function importEvidence(): void
    {
        $root = (string) config('gowa-updater.evidence_root', '');
        if ($root === '' || ! is_dir($root) || is_link($root)) {
            return;
        }
        $rootRealPath = realpath($root);
        if ($rootRealPath === false) {
            return;
        }

        $paths = array_merge(glob($root.'/*/*.json') ?: [], glob($root.'/*/*/*.json') ?: []);
        sort($paths);
        $paths = array_slice(array_unique($paths), 0, (int) config('gowa-updater.max_evidence_files', 256));
        $totalBytes = 0;
        foreach ($paths as $path) {
            $pathRealPath = realpath($path);
            if ($pathRealPath === false || ! str_starts_with($pathRealPath, $rootRealPath.'/')) {
                continue;
            }
            $size = filesize($path);
            if ($size === false || ($totalBytes + $size) > (int) config('gowa-updater.max_evidence_bytes', 16_777_216)) {
                continue;
            }
            $totalBytes += $size;
            try {
                $payload = $this->evidence->decode($path);
                $this->service->recordEvidence($payload);
                if (isset($payload['attestation']) && is_array($payload['attestation'])) {
                    $this->service->recordAttestation(array_merge($payload['attestation'], [
                        'operation_id' => $payload['operation_id'],
                        'fencing_token' => $payload['fencing_token'],
                    ]));
                }
                if (($payload['code'] ?? null) === 'rollback_degraded' && isset($payload['attestation']) && is_array($payload['attestation'])) {
                    $this->service->recordRuntimeAttestation($payload);
                    $this->service->commitVerifiedOutcome($this->operation($payload['operation_id']), 'degraded');
                }
                if (in_array($payload['code'] ?? null, ['mutation_observed', 'rollback_observed'], true)) {
                    $this->service->recordRuntimeAttestation($payload);
                    $this->service->commitVerifiedOutcome($this->operation($payload['operation_id']), $payload['code'] === 'rollback_observed' ? 'rolled_back' : 'succeeded');
                }
                @unlink($path);
            } catch (RuntimeException) {
                continue;
            }
        }
    }

    private function operation(string $id): GowaUpdateOperation
    {
        return GowaUpdateOperation::query()->findOrFail($id);
    }
}
