<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMutationMode;
use App\Models\InventoryOpeningBaseline;
use App\Models\InventoryOpeningBaselineLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryOpeningBaselineService
{
    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryLocationAccessService $locationAccess,
    ) {}

    public function generate(User $actor): InventoryOpeningBaseline
    {
        $this->assertAuthenticatedActor($actor);

        return $this->consistentTransaction(function () use ($actor): InventoryOpeningBaseline {
            [$lockedActor, $mode] = $this->authorizeBaselineActor($actor);
            if ($mode !== InventoryMutationMode::OPENING_BASELINE) {
                throw new AuthorizationException('Daftar stok awal hanya dapat dibuat saat pencatatan stok awal dimulai.');
            }

            $snapshot = $this->sourceSnapshot($lockedActor);
            $baseline = InventoryOpeningBaseline::query()->create([
                'status' => $snapshot['blockers'] === [] ? InventoryOpeningBaseline::STATUS_DRAFT : InventoryOpeningBaseline::STATUS_BLOCKED,
                'source_fingerprint' => $snapshot['fingerprint'],
                'policy_snapshot' => $snapshot['policy_snapshot'],
                'blockers' => $snapshot['blockers'],
                'tuple_count' => count($snapshot['lines']),
                'counted_count' => 0,
                'generated_by' => $lockedActor->id,
                'generated_at' => now(),
            ]);

            foreach (array_chunk($snapshot['lines'], self::INSERT_CHUNK_SIZE) as $chunk) {
                $now = now();
                InventoryOpeningBaselineLine::query()->insert(array_map(
                    static fn (array $line): array => [
                        'baseline_id' => $baseline->id,
                        ...$line,
                        'physical_qty' => null,
                        'counted_by' => null,
                        'counted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $chunk,
                ));
            }

            return $baseline->fresh();
        });
    }

    /** @return array{fingerprint:string,catalog_fingerprint:string,policy_snapshot:array,blockers:array,lines:array} */
    public function approvalSnapshotFor(User $actor): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Baseline source checks require an active database transaction.');
        }

        return $this->sourceSnapshot($actor);
    }

    /** Tuple picker data is advisory; mutations re-read and lock the tuple before accepting it. */
    public function previewSnapshotFor(User $actor): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Baseline source previews require an active database transaction.');
        }

        return $this->sourceSnapshot($actor, lockRows: false);
    }

    /** @return array{baseline: InventoryOpeningBaseline, lines: \Illuminate\Contracts\Pagination\LengthAwarePaginator, visible_line_count: int} */
    public function show(User $actor, int $baselineId, int $perPage = 50): array
    {
        return $this->consistentTransaction(function () use ($actor, $baselineId, $perPage): array {
            $this->authorizeBaselineActor($actor, allowApprover: true);
            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->lockForUpdate()->firstOrFail();
            $this->markStaleIfChanged($baseline, $actor);
            $visibleLocationIds = $this->locationAccess->visibleLocationIds($actor);
            $visibleLines = $baseline->lines()->whereIn('location_id', $visibleLocationIds);
            $lines = $visibleLines
                ->with(['item:id,item_code,name,item_type,uom', 'lot:id,item_id,lot_no,status', 'location:id,name,location_type'])
                ->orderBy('location_id')
                ->orderBy('item_id')
                ->orderByRaw('lot_id NULLS FIRST')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString();
            $visibleCountedCount = $baseline->lines()
                ->whereIn('location_id', $visibleLocationIds)
                ->whereNotNull('physical_qty')
                ->count();

            return [
                'baseline' => $baseline->fresh(),
                'lines' => $lines,
                'visible_line_count' => $visibleLines->count(),
                'visible_counted_count' => $visibleCountedCount,
            ];
        });
    }

    public function capture(User $actor, int $baselineId, int $lineId, float $physicalQty): InventoryOpeningBaselineLine
    {
        $this->assertAuthenticatedActor($actor);
        if ($physicalQty < 0 || $physicalQty > 99999999999.999) {
            throw ValidationException::withMessages(['physical_qty' => 'Jumlah fisik harus antara 0 dan 99.999.999.999,999.']);
        }

        $capturedLine = $this->consistentTransaction(function () use ($actor, $baselineId, $lineId, $physicalQty): ?InventoryOpeningBaselineLine {
            [$lockedActor, $mode] = $this->authorizeBaselineActor($actor);
            if ($mode !== InventoryMutationMode::OPENING_BASELINE) {
                throw new AuthorizationException('Pengisian jumlah hanya tersedia saat pencatatan stok awal berlangsung.');
            }

            $unlockedLine = InventoryOpeningBaselineLine::query()
                ->where('baseline_id', $baselineId)
                ->whereKey($lineId)
                ->firstOrFail();
            $location = InventoryLocation::query()->whereKey($unlockedLine->location_id)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($lockedActor, $location);

            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->lockForUpdate()->firstOrFail();
            if (! in_array($baseline->status, [InventoryOpeningBaseline::STATUS_DRAFT, InventoryOpeningBaseline::STATUS_BLOCKED], true)) {
                throw ValidationException::withMessages(['baseline' => 'Daftar sudah dikirim atau disetujui, sehingga jumlahnya tidak dapat diubah.']);
            }
            if ($baseline->status === InventoryOpeningBaseline::STATUS_STALE) {
                return null;
            }
            if ($this->markStaleIfChanged($baseline, $lockedActor)) {
                return null;
            }

            $line = InventoryOpeningBaselineLine::query()
                ->where('baseline_id', $baseline->id)
                ->whereKey($lineId)
                ->lockForUpdate()
                ->firstOrFail();
            $line->physical_qty = $physicalQty;
            $line->counted_by = $lockedActor->id;
            $line->counted_at = now();
            $line->save();

            $baseline->counted_count = $baseline->lines()->whereNotNull('physical_qty')->count();
            $baseline->save();

            return $line->fresh();
        });

        if ($capturedLine === null) {
            throw ValidationException::withMessages([
                'baseline' => 'Data berubah setelah daftar dibuat. Buat daftar baru dan hitung ulang barang yang terdampak.',
            ]);
        }

        return $capturedLine;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor baseline tidak sesuai dengan pengguna yang terautentikasi.');
        }
    }

    /** @return array{0: User, 1: string} */
    private function authorizeBaselineActor(User $actor, bool $allowApprover = false): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Opening baseline operations require a database transaction.');
        }

        try {
            $mode = InventoryMutationMode::query()
                ->whereKey(InventoryMutationMode::SINGLETON_ID)
                ->sharedLock()
                ->value('mode');
        } catch (QueryException $exception) {
            throw new AuthorizationException('Pencatatan stok awal belum dimulai.', previous: $exception);
        }

        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        $canSubmitOrApprove = $lockedActor
            && ($this->permissionService->userHasPermissionsForMutation($lockedActor, ['inventori.view', 'inventori.stocktake.submit'])
                || ($allowApprover && $this->permissionService->userHasPermissionsForMutation($lockedActor, ['inventori.view', 'inventori.stocktake.approve'])));
        if (! $lockedActor || ! $lockedActor->is_active || ! $canSubmitOrApprove) {
            throw new AuthorizationException('Anda tidak memiliki izin membuat atau mengisi catatan stok awal.');
        }

        return [$lockedActor, (string) $mode];
    }

    /** @return array{fingerprint:string,policy_snapshot:array<string,mixed>,blockers:array<string,mixed>,lines:list<array<string,mixed>>} */
    private function sourceSnapshot(User $actor, bool $lockRows = true): array
    {
        $locationsQuery = InventoryLocation::query()->orderBy('id');
        if ($lockRows) {
            $locationsQuery->lockForUpdate();
        }
        $locations = $locationsQuery->get(['id', 'name', 'location_type', 'is_restricted', 'updated_at']);
        $restrictedLocationIds = $locations->where('is_restricted', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $grantsQuery = $restrictedLocationIds === []
            ? null
            : InventoryLocationAccess::query()
                ->where('user_id', $actor->id)
                ->whereIn('location_id', $restrictedLocationIds)
                ->orderBy('location_id');
        if ($grantsQuery && $lockRows) {
            $grantsQuery->lockForUpdate();
        }
        $grants = $grantsQuery?->get()->keyBy('location_id') ?? collect();
        $itemsQuery = InventoryItem::query()->orderBy('id');
        if ($lockRows) {
            $itemsQuery->lockForUpdate();
        }
        $items = $itemsQuery->get(['id', 'item_code', 'name', 'item_type', 'uom', 'is_active', 'updated_at']);
        $lotsQuery = InventoryLot::query()->orderBy('id');
        if ($lockRows) {
            $lotsQuery->lockForUpdate();
        }
        $lots = $lotsQuery->get(['id', 'item_id', 'lot_no', 'status', 'expiry_date', 'updated_at']);
        $balancesQuery = DB::table('inventory_balances')->orderBy('item_id')->orderByRaw('lot_id NULLS FIRST')->orderBy('location_id')->orderBy('id');
        if ($lockRows) {
            $balancesQuery->lockForUpdate();
        }
        $balances = $balancesQuery->get([
            'id', 'item_id', 'lot_id', 'location_id', 'on_hand_qty', 'reserved_qty', 'updated_at',
        ]);
        $ledgerByTuple = $this->ledgerQuantities();
        $policies = InventoryLotTrackingPolicy::query()->orderBy('item_type')->get()->keyBy('item_type');

        $itemsById = $items->keyBy('id');
        $lotsById = $lots->keyBy('id');
        $locationsById = $locations->keyBy('id');
        $visibleLocationIds = $locations->filter(function (InventoryLocation $location) use ($grants): bool {
            return ! $location->is_restricted || (bool) $grants->get($location->id)?->can_view;
        })->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $visibleLocationIdSet = array_fill_keys($visibleLocationIds, true);
        $restrictedLocationIdsMissingViewGrant = $locations
            ->filter(fn (InventoryLocation $location): bool => $location->is_restricted && ! isset($visibleLocationIdSet[$location->id]))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $balanceGroups = $balances->groupBy(fn (object $balance): string => $this->tupleKey(
            (int) $balance->item_id,
            $balance->lot_id === null ? null : (int) $balance->lot_id,
            (int) $balance->location_id,
        ));
        $tuples = [];
        $addTuple = function (int $itemId, ?int $lotId, int $locationId) use (&$tuples): void {
            $tuples[$this->tupleKey($itemId, $lotId, $locationId)] = [
                'item_id' => $itemId,
                'lot_id' => $lotId,
                'location_id' => $locationId,
            ];
        };

        foreach ($balances as $balance) {
            $addTuple((int) $balance->item_id, $balance->lot_id === null ? null : (int) $balance->lot_id, (int) $balance->location_id);
        }
        foreach ($ledgerByTuple as $ledger) {
            $addTuple($ledger['item_id'], $ledger['lot_id'], $ledger['location_id']);
        }

        $blockers = [
            'unclassified_item_types' => [],
            'restricted_location_view_grants_missing' => $restrictedLocationIdsMissingViewGrant !== [],
            'restricted_location_ids_missing_view_grant' => $restrictedLocationIdsMissingViewGrant,
            'duplicate_balance_tuples' => 0,
            'ledger_projection_mismatch_tuples' => 0,
            'missing_master_identity_tuples' => 0,
        ];
        $activeItems = $this->locationAccess->itemsForView(InventoryItem::query()->active(), $actor)->get();
        $activeCountsByType = $activeItems->countBy('item_type');
        $policySnapshot = [];
        foreach (InventoryItem::ITEM_TYPES as $itemType => $label) {
            $policy = $policies->get($itemType);
            $policySnapshot[$itemType] = [
                'label' => $label,
                'lot_tracking_mode' => $policy?->lot_tracking_mode,
                'evidence_reference' => $policy?->evidence_reference,
                'decided_by_user_id' => $policy?->decided_by_user_id,
                'decided_at' => $policy?->decided_at?->toISOString(),
            ];
            if (! $activeCountsByType->has($itemType)) {
                continue;
            }
            if (! in_array($policy?->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                || trim((string) $policy?->evidence_reference) === ''
                || $policy?->decided_by_user_id === null
                || $policy?->decided_at === null) {
                $blockers['unclassified_item_types'][] = [
                    'item_type' => $itemType,
                    'label' => $label,
                    'active_item_count' => (int) $activeCountsByType->get($itemType),
                ];

                continue;
            }

            $typeItems = $activeItems->where('item_type', $itemType);
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::UNTRACKED) {
                foreach ($typeItems as $item) {
                    foreach ($locations as $location) {
                        $addTuple((int) $item->id, null, (int) $location->id);
                    }
                }

                continue;
            }

            $itemLots = $lots->whereIn('item_id', $typeItems->pluck('id')->all())->where('status', '!=', 'DISPOSED');
            foreach ($typeItems as $item) {
                foreach ($itemLots->where('item_id', $item->id) as $lot) {
                    foreach ($locations as $location) {
                        $addTuple((int) $item->id, (int) $lot->id, (int) $location->id);
                    }
                }
            }
        }

        $policyVersion = hash('sha256', json_encode($policySnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $versionedPolicySnapshot = [
            'version' => $policyVersion,
            'item_type_policies' => $policySnapshot,
        ];

        $lines = [];
        foreach ($tuples as $tuple) {
            $key = $this->tupleKey($tuple['item_id'], $tuple['lot_id'], $tuple['location_id']);
            $location = $locationsById->get($tuple['location_id']);
            if (! $location) {
                $blockers['missing_master_identity_tuples']++;

                continue;
            }
            if (! isset($visibleLocationIdSet[$location->id])) {
                continue;
            }
            $balanceRows = $balanceGroups->get($key, collect());
            if ($balanceRows->count() > 1) {
                $blockers['duplicate_balance_tuples']++;
            }

            $item = $itemsById->get($tuple['item_id']);
            $lot = $tuple['lot_id'] === null ? null : $lotsById->get($tuple['lot_id']);
            if (! $item || ($tuple['lot_id'] !== null && ! $lot)) {
                $blockers['missing_master_identity_tuples']++;

                continue;
            }

            $onHand = (float) $balanceRows->sum('on_hand_qty');
            $reserved = (float) $balanceRows->sum('reserved_qty');
            $ledgerOnHand = (float) ($ledgerByTuple[$key]['ledger_on_hand_qty'] ?? 0);
            if (abs($onHand - $ledgerOnHand) >= 0.001) {
                $blockers['ledger_projection_mismatch_tuples']++;
            }

            $lines[] = [
                ...$tuple,
                'item_code_snapshot' => $item->item_code,
                'item_name_snapshot' => $item->name,
                'item_type_snapshot' => $item->item_type,
                'uom_snapshot' => $item->uom,
                'lot_no_snapshot' => $lot?->lot_no,
                'location_name_snapshot' => $location->name,
                'location_type_snapshot' => $location->location_type,
                'system_on_hand_qty' => $onHand,
                'ledger_on_hand_qty' => $ledgerOnHand,
                'reserved_qty_snapshot' => $reserved,
                'balance_existed' => $balanceRows->isNotEmpty(),
            ];
        }

        $unknownTypes = $activeItems->pluck('item_type')->unique()->reject(fn (string $type): bool => array_key_exists($type, InventoryItem::ITEM_TYPES));
        foreach ($unknownTypes as $type) {
            $blockers['unclassified_item_types'][] = [
                'item_type' => $type,
                'label' => 'Tipe item tidak dikenal',
                'active_item_count' => (int) $activeCountsByType->get($type),
            ];
        }

        usort($lines, static fn (array $left, array $right): int => [$left['item_id'], $left['lot_id'] ?? 0, $left['location_id']] <=> [$right['item_id'], $right['lot_id'] ?? 0, $right['location_id']]);
        $fingerprintSource = [
            'items' => $items->map(fn (InventoryItem $item): array => [$item->id, $item->item_code, $item->name, $item->item_type, $item->uom, $item->is_active, $item->updated_at?->toISOString()])->all(),
            'lots' => $lots->map(fn (InventoryLot $lot): array => [$lot->id, $lot->item_id, $lot->lot_no, $lot->status, $lot->expiry_date?->toDateString(), $lot->updated_at?->toISOString()])->all(),
            'locations' => $locations->map(fn (InventoryLocation $location): array => [$location->id, $location->name, $location->location_type, $location->is_restricted, $location->updated_at?->toISOString()])->all(),
            'balances' => $balances->map(fn (object $balance): array => [$balance->id, $balance->item_id, $balance->lot_id, $balance->location_id, $balance->on_hand_qty, $balance->reserved_qty, $balance->updated_at])->all(),
            'ledger' => collect($ledgerByTuple)->sortKeys()->values()->all(),
            'policy' => $versionedPolicySnapshot,
            'count_team_grants' => $grants->map(fn (InventoryLocationAccess $grant): array => [$grant->location_id, $grant->can_view, $grant->updated_at?->toISOString()])->values()->all(),
        ];
        $catalogFingerprintSource = [
            'items' => $fingerprintSource['items'],
            'lots' => $fingerprintSource['lots'],
            'locations' => $fingerprintSource['locations'],
            'policy' => $versionedPolicySnapshot,
            'count_team_grants' => $fingerprintSource['count_team_grants'],
        ];

        return [
            'fingerprint' => hash('sha256', json_encode($fingerprintSource, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'catalog_fingerprint' => hash('sha256', json_encode($catalogFingerprintSource, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'policy_snapshot' => $versionedPolicySnapshot,
            'blockers' => array_filter($blockers, static fn (mixed $value): bool => $value !== [] && $value !== 0 && $value !== false && $value !== null),
            'lines' => $lines,
        ];
    }

    /** @return array<string, array{item_id:int,lot_id:?int,location_id:int,ledger_on_hand_qty:string}> */
    private function ledgerQuantities(): array
    {
        $toLocation = DB::table('inventory_movements')
            ->whereNotNull('to_location_id')
            ->selectRaw("item_id, lot_id, to_location_id AS location_id, CASE WHEN movement_type IN ('RECEIPT', 'RETURN', 'TRANSFER', 'ADJUST') THEN qty ELSE 0 END AS delta");
        $fromLocation = DB::table('inventory_movements')
            ->whereNotNull('from_location_id')
            ->selectRaw("item_id, lot_id, from_location_id AS location_id, CASE WHEN movement_type IN ('ISSUE', 'TRANSFER', 'DISPOSE', 'ADJUST') THEN -qty ELSE 0 END AS delta");
        $ledgerEndpoints = $toLocation->unionAll($fromLocation);

        return DB::query()
            ->fromSub($ledgerEndpoints, 'inventory_movement_endpoints')
            ->select('item_id', 'lot_id', 'location_id')
            ->selectRaw('SUM(delta) AS ledger_on_hand_qty')
            ->groupBy('item_id', 'lot_id', 'location_id')
            ->orderBy('item_id')
            ->orderBy('location_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $this->tupleKey((int) $row->item_id, $row->lot_id === null ? null : (int) $row->lot_id, (int) $row->location_id) => [
                    'item_id' => (int) $row->item_id,
                    'lot_id' => $row->lot_id === null ? null : (int) $row->lot_id,
                    'location_id' => (int) $row->location_id,
                    'ledger_on_hand_qty' => (string) $row->ledger_on_hand_qty,
                ],
            ])->all();
    }

    private function tupleKey(int $itemId, ?int $lotId, int $locationId): string
    {
        return $itemId.'|'.($lotId === null ? 'null' : (string) $lotId).'|'.$locationId;
    }

    private function markStaleIfChanged(InventoryOpeningBaseline $baseline, User $actor): bool
    {
        if ($baseline->status === InventoryOpeningBaseline::STATUS_APPROVED
            && InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') !== InventoryMutationMode::OPENING_BASELINE) {
            return false;
        }

        $manifestOwner = User::query()->whereKey($baseline->generated_by)->firstOrFail();
        $source = $this->sourceSnapshot($manifestOwner);
        $expectedFingerprint = $baseline->status === InventoryOpeningBaseline::STATUS_APPROVED
            ? $baseline->approvals()->where('decision', 'approved')->orderByDesc('submission_revision')->value('resulting_source_fingerprint')
            : $baseline->source_fingerprint;
        if ($expectedFingerprint !== null && hash_equals($expectedFingerprint, $source['fingerprint'])) {
            return false;
        }

        if ($baseline->status !== InventoryOpeningBaseline::STATUS_STALE) {
            $baseline->status = InventoryOpeningBaseline::STATUS_STALE;
            $baseline->stale_at = now();
            $baseline->blockers = [...($baseline->blockers ?? []), 'manifest_stale'];
            $baseline->save();
        }

        return true;
    }

    private function consistentTransaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback): mixed {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $callback();
        });
    }
}
