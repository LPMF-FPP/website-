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
        $count = 0;
        GowaUpdateOperation::query()->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)
            ->where('lease_expires_at', '<', now())->each(function (GowaUpdateOperation $operation) use (&$count): void {
                $this->service->reconcileStale($operation);
                $count++;
            });

        return $count;
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

        foreach (glob($root.'/*/*.json') ?: [] as $path) {
            $pathRealPath = realpath($path);
            if ($pathRealPath === false || ! str_starts_with($pathRealPath, $rootRealPath.'/')) {
                continue;
            }
            try {
                $payload = $this->evidence->decode($path);
                $this->service->recordEvidence($payload);
                if (isset($payload['attestation']) && is_array($payload['attestation'])) {
                    $this->service->recordAttestation(array_merge($payload['attestation'], [
                        'operation_id' => $payload['operation_id'],
                        'fencing_token' => $payload['fencing_token'],
                    ]));
                }
                unlink($path);
            } catch (RuntimeException) {
                continue;
            }
        }
    }
}
