<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Models\GowaUpdateDispatchClaim;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class GowaUpdateClaimService
{
    public function __construct(private readonly GowaReleaseCatalog $catalog) {}

    /** @return array<string, scalar|null> */
    public function claim(GowaUpdateOperation $operation, string $owner = 'gowa-maintenance'): array
    {
        return DB::transaction(function () use ($operation, $owner): array {
            $scope = GowaUpdateScope::query()->whereKey($operation->scope)->lockForUpdate()->first();
            $locked = GowaUpdateOperation::query()->lockForUpdate()->find($operation->id);
            if ($scope === null || $locked === null || $locked->scope !== GowaUpdateOperation::SCOPE) {
                throw new RuntimeException('claim_rejected');
            }

            $release = $this->catalog->find($locked->release_id);
            $generation = $this->catalog->generation();
            if ($release === null) {
                throw new RuntimeException('release_not_allowed');
            }
            if ($generation === null || $scope->active_operation_id !== $locked->id
                || ! in_array($locked->status, GowaUpdateOperation::ACTIVE_STATUSES, true)
                || $locked->fencing_token !== (int) $scope->current_fence
                || $locked->lease_expires_at?->isPast() === true) {
                throw new RuntimeException('claim_rejected');
            }

            $existing = GowaUpdateDispatchClaim::query()->where('operation_id', $locked->id)->first();
            if ($existing !== null) {
                if ($existing->owner !== $owner || $existing->release_id !== $release['release_id']
                    || $existing->fencing_token !== $locked->fencing_token
                    || $existing->catalog_generation !== $generation) {
                    throw new RuntimeException('claim_payload_mismatch');
                }

                return array_merge($this->payload($existing), ['replayed' => true]);
            }

            $nonce = (string) Str::uuid();
            $payload = [
                'operation_id' => (string) $locked->id,
                'scope' => (string) $locked->scope,
                'release_id' => (string) $release['release_id'],
                'digest' => (string) $release['digest'],
                'fencing_token' => (int) $locked->fencing_token,
                'claim_nonce' => $nonce,
                'catalog_generation' => $generation,
                'revocation_generation' => (string) ($release['revocation_generation'] ?? 'initial'),
                'lease_expires_at' => $locked->lease_expires_at?->toIso8601String(),
            ];
            $claim = GowaUpdateDispatchClaim::query()->create([
                'operation_id' => $locked->id,
                'scope' => $locked->scope,
                'release_id' => $release['release_id'],
                'fencing_token' => $locked->fencing_token,
                'claim_nonce' => $nonce,
                'owner' => $owner,
                'catalog_generation' => $generation,
                'revocation_generation' => $payload['revocation_generation'],
                'claim_payload' => $payload,
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'claimed_at' => now(),
                'lease_expires_at' => $locked->lease_expires_at,
                'consumed_at' => now(),
            ]);

            return array_merge($this->payload($claim), ['replayed' => false]);
        });
    }

    /** @return array<string, scalar|null> */
    private function payload(GowaUpdateDispatchClaim $claim): array
    {
        $payload = $claim->claim_payload;
        if (! is_array($payload) || ! hash_equals($claim->payload_hash, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('claim_rejected');
        }

        return $payload;
    }
}
