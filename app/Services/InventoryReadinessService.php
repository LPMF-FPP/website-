<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryReadinessEvidence;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReadinessService
{
    private const ROLES = [
        'inventory_administrator' => 'inventori.readiness.inventory-admin.accept',
        'system_owner' => 'inventori.readiness.system-owner.accept',
    ];

    public function __construct(private readonly PermissionService $permissionService) {}

    public function recordEvidence(User $actor, string $category, string $reference, string $role): InventoryReadinessEvidence
    {
        $this->assertAuthenticatedActor($actor);
        if (! array_key_exists($category, InventoryReadinessEvidence::CATEGORIES)) {
            throw ValidationException::withMessages(['category' => 'Kategori bukti kesiapan tidak dikenal.']);
        }
        if (! array_key_exists($role, self::ROLES)) {
            throw ValidationException::withMessages(['role' => 'Peran penerima bukti kesiapan tidak dikenal.']);
        }
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['evidence_reference' => 'Referensi bukti kesiapan wajib diisi.']);
        }

        return DB::transaction(function () use ($actor, $category, $reference, $role): InventoryReadinessEvidence {
            $this->lockBaselineMode();
            $lockedActor = $this->lockAuthorizedActor($actor, ['inventori.view', self::ROLES[$role]]);
            $stateFingerprint = $this->currentStateFingerprint($category);
            $latest = InventoryReadinessEvidence::query()
                ->where('category', $category)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            if ($latest && hash_equals($latest->evidence_reference, $reference)
                && $latest->state_fingerprint === $stateFingerprint) {
                $existing = $latest->signoffs()->where('signoff_role', $role)->first();
                if ($existing && $existing->actor_user_id === $lockedActor->id) {
                    return $latest;
                }
                if ($existing) {
                    throw new AuthorizationException('Bukti terbaru sudah memiliki sign-off untuk peran ini.');
                }
                if ($latest->signoffs()->where('actor_user_id', $lockedActor->id)->exists()) {
                    throw new AuthorizationException('Dua sign-off kesiapan harus berasal dari pengguna yang berbeda.');
                }

                $latest->signoffs()->create([
                    'signoff_role' => $role,
                    'actor_user_id' => $lockedActor->id,
                    'signed_at' => now(),
                ]);

                return $latest;
            }

            $evidence = InventoryReadinessEvidence::query()->create([
                'category' => $category,
                'version' => ($latest?->version ?? 0) + 1,
                'evidence_reference' => $reference,
                'state_fingerprint' => $stateFingerprint,
                'recorded_by' => $lockedActor->id,
                'recorded_at' => now(),
            ]);

            $evidence->signoffs()->create([
                'signoff_role' => $role,
                'actor_user_id' => $lockedActor->id,
                'signed_at' => now(),
            ]);

            return $evidence;
        });
    }

    /** @return list<string> */
    public function missingCategories(): array
    {
        $missing = [];
        foreach (InventoryReadinessEvidence::CATEGORIES as $category => $_label) {
            $evidence = InventoryReadinessEvidence::query()->where('category', $category)
                ->with('signoffs:id,evidence_id,signoff_role,actor_user_id')
                ->orderByDesc('version')
                ->first();
            $signoffs = $evidence?->signoffs ?? collect();
            $actorsByRole = $signoffs->pluck('actor_user_id', 'signoff_role');
            $currentStateFingerprint = $this->currentStateFingerprint($category);
            if (! $evidence
                || trim($evidence->evidence_reference) === ''
                || $evidence->state_fingerprint !== $currentStateFingerprint
                || ! $actorsByRole->has('inventory_administrator')
                || ! $actorsByRole->has('system_owner')
                || $actorsByRole->get('inventory_administrator') === $actorsByRole->get('system_owner')) {
                $missing[] = $category;
            }
        }

        return $missing;
    }

    public function evidenceFingerprint(): string
    {
        $evidence = [];
        foreach (InventoryReadinessEvidence::CATEGORIES as $category => $_label) {
            $latest = InventoryReadinessEvidence::query()->where('category', $category)
                ->with('signoffs:id,evidence_id,signoff_role,actor_user_id,signed_at')
                ->orderByDesc('version')
                ->first();
            $evidence[$category] = $latest ? [
                'id' => $latest->id,
                'version' => $latest->version,
                'reference' => $latest->evidence_reference,
                'state_fingerprint' => $latest->state_fingerprint,
                'signoffs' => $latest->signoffs->sortBy('signoff_role')->map(fn ($signoff): array => [
                    $signoff->signoff_role,
                    $signoff->actor_user_id,
                    $signoff->signed_at?->toISOString(),
                ])->values()->all(),
            ] : null;
        }

        return hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{items:array<string, array{label:string,evidence:?InventoryReadinessEvidence,complete:bool}>,missing:list<string>} */
    public function overview(): array
    {
        $missing = $this->missingCategories();
        $items = [];
        foreach (InventoryReadinessEvidence::CATEGORIES as $category => $label) {
            $evidence = InventoryReadinessEvidence::query()->where('category', $category)
                ->with('signoffs.actor:id,name')
                ->orderByDesc('version')
                ->first();
            $items[$category] = [
                'label' => $label,
                'evidence' => $evidence,
                'complete' => $evidence !== null && ! in_array($category, $missing, true),
            ];
        }

        return ['items' => $items, 'missing' => $missing];
    }

    private function lockAuthorizedActor(User $actor, array $permissions): User
    {
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, $permissions)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengelola bukti kesiapan inventori.');
        }

        return $lockedActor;
    }

    private function lockBaselineMode(): void
    {
        $mode = DB::table('inventory_mutation_modes')->where('id', 1)->lockForUpdate()->value('mode');
        if ($mode !== 'opening_baseline') {
            throw new AuthorizationException('Bukti kesiapan hanya dapat diubah saat mode opening_baseline.');
        }
    }

    private function currentStateFingerprint(string $category): ?string
    {
        if ($category === 'permission_grants') {
            $permissions = DB::table('permissions')->where('module', 'inventori')->orderBy('id')->get(['id', 'name']);
            $permissionIds = $permissions->pluck('id')->all();
            $state = [
                'permissions' => $permissions->all(),
                'users' => DB::table('users')->orderBy('id')->get(['id', 'role', 'is_active'])->all(),
                'role_permissions' => DB::table('role_permissions')->whereIn('permission_id', $permissionIds)
                    ->orderBy('role')->orderBy('permission_id')->get(['role', 'permission_id'])->all(),
                'user_permissions' => DB::table('user_permissions')->whereIn('permission_id', $permissionIds)
                    ->orderBy('user_id')->orderBy('permission_id')->get(['user_id', 'permission_id', 'granted'])->all(),
                'location_grants' => DB::table('inventory_location_access')->orderBy('user_id')->orderBy('location_id')
                    ->get(['user_id', 'location_id', 'can_view', 'can_transact'])->all(),
            ];

            return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if ($category === 'lot_item_policies') {
            $state = DB::table('inventory_lot_tracking_policies')->orderBy('item_type')
                ->get(['item_type', 'lot_tracking_mode', 'evidence_reference', 'decided_by_user_id', 'decided_at', 'updated_at'])->all();

            return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return null;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor bukti kesiapan tidak sesuai dengan pengguna yang terautentikasi.');
        }
    }
}
