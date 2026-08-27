<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GowaUpdateClaimService
{
    public function __construct(private readonly GowaReleaseCatalog $catalog) {}

    /** @return array<string, scalar|null> */
    public function claim(GowaUpdateOperation $operation, string $owner = 'gowa-maintenance'): array
    {
        return $this->requestRootClaim($operation, $owner);
    }

    /**
     * Validates the application-side snapshot. The UUID is the only value
     * crossing into the root helper; the helper claims the nonce in PostgreSQL.
     *
     * @return array<string, scalar|null>
     */
    public function requestRootClaim(GowaUpdateOperation $operation, string $owner = 'gowa-maintenance'): array
    {
        if (DB::connection()->getDriverName() === 'pgsql' && ! app()->environment('testing')) {
            return $this->claimViaUpdaterGateway($operation, $owner);
        }

        return DB::transaction(function () use ($operation, $owner): array {
            $scope = GowaUpdateScope::query()->whereKey($operation->scope)->lockForUpdate()->first();
            $locked = GowaUpdateOperation::query()->lockForUpdate()->find($operation->id);
            $release = $locked === null ? null : $this->catalog->find($locked->release_id);
            $generation = $this->catalog->generation();

            if ($scope === null || $locked === null || $release === null || $generation === null
                || $owner !== 'gowa-maintenance'
                || $locked->scope !== GowaUpdateOperation::SCOPE
                || $scope->active_operation_id !== $locked->id
                || ! in_array($locked->status, GowaUpdateOperation::ACTIVE_STATUSES, true)
                || $locked->fencing_token !== (int) $scope->current_fence
                || $locked->lease_expires_at?->isPast() === true) {
                throw new RuntimeException($release === null ? 'release_not_allowed' : 'claim_rejected');
            }

            if ($release['release_id'] !== $locked->release_id || $release['digest'] !== $locked->requested_digest
                || $generation !== ($locked->feature_snapshot['catalog_generation'] ?? null)
                || ($release['revocation_generation'] ?? 'initial') !== ($locked->feature_snapshot['revocation_generation'] ?? null)) {
                throw new RuntimeException('claim_payload_mismatch');
            }

            return ['operation_id' => (string) $locked->id, 'replayed' => false];
        });
    }

    /** @return array<string, scalar|null> */
    private function claimViaUpdaterGateway(GowaUpdateOperation $operation, string $owner): array
    {
        try {
            $result = DB::selectOne(
                'select updater_gateway.claim_dispatch(?::uuid, ?::text) as result',
                [(string) $operation->id, $owner],
            );
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $code = str_contains($message, 'claim_payload_mismatch') ? 'claim_payload_mismatch' : 'claim_rejected';
            throw new RuntimeException($code, 0, $exception);
        }

        $decoded = json_decode((string) ($result->result ?? ''), true);
        if (! is_array($decoded) || ! is_array($decoded['payload'] ?? null)) {
            throw new RuntimeException('claim_rejected');
        }

        return array_merge($decoded['payload'], ['replayed' => (bool) ($decoded['replayed'] ?? false)]);
    }
}
