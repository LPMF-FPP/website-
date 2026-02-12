<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentLock;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QmhRevisionLockService
{
    public function acquire(QmhDocumentRevision $revision, int $actorId): QmhDocumentLock
    {
        return DB::transaction(function () use ($revision, $actorId) {
            $lock = QmhDocumentLock::query()
                ->where('revision_id', $revision->id)
                ->lockForUpdate()
                ->first();

            if ($lock !== null && $lock->isActive() && $lock->locked_by !== $actorId) {
                throw new ConflictHttpException('Revisi sedang dikunci oleh pengguna lain.');
            }

            $now = now();
            $expiresAt = now()->addMinutes(30);

            if ($lock === null) {
                $lock = new QmhDocumentLock;
                $lock->revision_id = $revision->id;
            }

            $lock->locked_by = $actorId;
            $lock->locked_at = $now;
            $lock->heartbeat_at = $now;
            $lock->expires_at = $expiresAt;
            $lock->force_unlocked_by = null;
            $lock->force_unlocked_reason = null;
            $lock->save();

            $this->persistWorkflowEvent($revision->id, $actorId, 'lock', [
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $lock->fresh();
        });
    }

    public function heartbeat(QmhDocumentRevision $revision, int $actorId): QmhDocumentLock
    {
        return DB::transaction(function () use ($revision, $actorId) {
            $lock = QmhDocumentLock::query()
                ->where('revision_id', $revision->id)
                ->lockForUpdate()
                ->first();

            if ($lock === null || ! $lock->isActive()) {
                throw new ConflictHttpException('Tidak ada lock aktif untuk revisi ini.');
            }

            if ($lock->locked_by !== $actorId) {
                throw new AuthorizationException('Hanya pemilik lock yang dapat mengirim heartbeat.');
            }

            $now = now();
            $expiresAt = now()->addMinutes(30);

            $lock->heartbeat_at = $now;
            $lock->expires_at = $expiresAt;
            $lock->save();

            return $lock->fresh();
        });
    }

    public function unlock(QmhDocumentRevision $revision, int $actorId, bool $force = false, ?string $reason = null): QmhDocumentLock
    {
        return DB::transaction(function () use ($revision, $actorId, $force, $reason) {
            $lock = QmhDocumentLock::query()
                ->where('revision_id', $revision->id)
                ->lockForUpdate()
                ->first();

            if ($lock === null || ! $lock->isActive()) {
                throw new ConflictHttpException('Tidak ada lock aktif untuk dibuka.');
            }

            if ($force) {
                if ($reason === null || trim($reason) === '') {
                    throw ValidationException::withMessages([
                        'reason' => 'Alasan wajib diisi saat force unlock.',
                    ]);
                }

                $lock->force_unlocked_by = $actorId;
                $lock->force_unlocked_reason = $reason;
            } elseif ($lock->locked_by !== $actorId) {
                throw new AuthorizationException('Hanya pemilik lock yang dapat membuka lock.');
            }

            $lock->expires_at = now();
            $lock->save();

            $this->persistWorkflowEvent($revision->id, $actorId, 'unlock', [
                'force' => $force,
                'reason' => $reason,
            ]);

            return $lock->fresh();
        });
    }

    protected function persistWorkflowEvent(int $revisionId, int $actorId, string $eventType, array $payload): void
    {
        QmhWorkflowEvent::query()->create([
            'revision_id' => $revisionId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'payload_json' => $payload,
        ]);
    }
}
