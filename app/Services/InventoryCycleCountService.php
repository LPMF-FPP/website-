<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryCycleCount;
use App\Models\InventoryCycleCountDecision;
use App\Models\InventoryCycleCountLine;
use App\Models\InventoryCycleCountRevision;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCycleCountService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryOpeningBaselineService $baselineService,
    ) {}

    public function createDraft(User $actor, ?string $purpose = null): InventoryCycleCount
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($actor, $purpose): InventoryCycleCount {
            $lockedActor = $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);

            return InventoryCycleCount::query()->create([
                'status' => InventoryCycleCount::STATUS_DRAFT,
                'is_partial' => true,
                'purpose' => $purpose !== null ? trim($purpose) : null,
                'created_by' => $lockedActor->id,
            ]);
        });
    }

    /** @return list<array<string, mixed>> */
    public function availableTuples(User $actor): array
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($actor): array {
            $lockedActor = $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);
            $snapshot = $this->baselineService->previewSnapshotFor($lockedActor);
            $transactLocationIds = $this->locationAccess->transactLocationIds($lockedActor)->pluck('id')->map(fn ($id): int => (int) $id)->all();

            return array_values(array_filter($snapshot['lines'], fn (array $line): bool => in_array((int) $line['location_id'], $transactLocationIds, true)));
        });
    }

    public function addTuple(User $actor, int $cycleCountId, int $itemId, ?int $lotId, int $locationId): InventoryCycleCountLine
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($actor, $cycleCountId, $itemId, $lotId, $locationId): InventoryCycleCountLine {
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->firstOrFail();
            $lockedActor = $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->lockForUpdate()->firstOrFail();
            $this->assertEditableBy($count, $lockedActor);
            $source = $this->baselineService->approvalSnapshotFor($lockedActor);

            $sourceLine = collect($source['lines'])->first(fn (array $line): bool => (int) $line['item_id'] === $itemId
                && ($line['lot_id'] === null ? null : (int) $line['lot_id']) === $lotId
                && (int) $line['location_id'] === $locationId
            );
            if ($sourceLine === null) {
                throw new AuthorizationException('Barang, nomor lot, atau lokasi tidak tersedia untuk dilihat.');
            }
            $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($lockedActor, $location);
            $this->locationAccess->authorizeLockedTransaction($lockedActor, [$location->id]);
            $this->assertTupleIntegrity($sourceLine);

            $line = InventoryCycleCountLine::query()->firstOrCreate([
                'cycle_count_id' => $count->id,
                'item_id' => $itemId,
                'lot_id' => $lotId,
                'location_id' => $locationId,
            ], [
                ...$this->sourceSnapshotAttributes($sourceLine),
            ]);
            if (! $line->wasRecentlyCreated) {
                throw ValidationException::withMessages(['tuple' => 'Barang ini sudah ada dalam daftar pemeriksaan.']);
            }

            $count->tuple_count = $count->lines()->count();
            $count->save();

            return $line;
        });
    }

    public function removeTuple(User $actor, int $cycleCountId, int $lineId): void
    {
        $this->assertAuthenticatedActor($actor);
        DB::transaction(function () use ($actor, $cycleCountId, $lineId): void {
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->firstOrFail();
            $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->lockForUpdate()->firstOrFail();
            $this->assertEditableBy($count, $actor);
            $line = InventoryCycleCountLine::query()->where('cycle_count_id', $count->id)->whereKey($lineId)->lockForUpdate()->firstOrFail();
            $line->delete();
            $count->tuple_count = $count->lines()->count();
            $count->counted_count = $count->lines()->whereNotNull('physical_qty')->count();
            $count->save();
        });
    }

    public function capture(User $actor, int $cycleCountId, int $lineId, float $physicalQty, ?string $varianceReason = null): InventoryCycleCountLine
    {
        $this->assertAuthenticatedActor($actor);
        if ($physicalQty < 0 || $physicalQty > 99999999999.999) {
            throw ValidationException::withMessages(['physical_qty' => 'Jumlah fisik harus antara 0 dan 99.999.999.999,999.']);
        }
        if ($varianceReason !== null && mb_strlen($varianceReason) > 1000) {
            throw ValidationException::withMessages(['variance_reason' => 'Alasan selisih maksimal 1.000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $cycleCountId, $lineId, $physicalQty, $varianceReason): InventoryCycleCountLine {
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->firstOrFail();
            $lockedActor = $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->lockForUpdate()->firstOrFail();
            $this->assertEditableBy($count, $lockedActor);
            $source = $this->baselineService->approvalSnapshotFor($lockedActor);
            $line = InventoryCycleCountLine::query()->where('cycle_count_id', $count->id)->whereKey($lineId)->lockForUpdate()->firstOrFail();
            $sourceLine = $this->sourceLineFor($source['lines'], $line);
            if ($sourceLine === null || ! hash_equals($line->source_tuple_fingerprint, $this->tupleFingerprint($sourceLine))) {
                throw ValidationException::withMessages(['cycle_count' => 'Data barang atau jumlah stok berubah sejak dipilih. Hapus barang ini, lalu tambahkan kembali sebelum menghitung.']);
            }

            $line->physical_qty = $physicalQty;
            $line->variance_reason = $varianceReason !== null ? trim($varianceReason) : null;
            $line->counted_by = $lockedActor->id;
            $line->counted_at = now();
            $line->save();
            $count->counted_count = $count->lines()->whereNotNull('physical_qty')->count();
            $count->save();

            return $line->fresh();
        });
    }

    public function submit(User $actor, int $cycleCountId): InventoryCycleCount
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($actor, $cycleCountId): InventoryCycleCount {
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->firstOrFail();
            $lockedActor = $this->authorizeActor($actor, ['inventori.view', 'inventori.stocktake.submit']);
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->lockForUpdate()->firstOrFail();
            $this->assertEditableBy($count, $lockedActor);
            $source = $this->baselineService->approvalSnapshotFor($lockedActor);
            $lines = $count->lines()->orderBy('item_id')->orderByRaw('lot_id NULLS FIRST')->orderBy('location_id')->lockForUpdate()->get();
            if ($count->tuple_count < 1
                || $count->counted_count !== $count->tuple_count
                || $lines->count() !== $count->tuple_count
                || $lines->contains(fn (InventoryCycleCountLine $line): bool => $line->physical_qty === null)) {
                throw ValidationException::withMessages(['cycle_count' => 'Isi jumlah fisik semua barang yang dipilih sebelum mengirim hasil hitung.']);
            }
            $snapshot = $this->buildRevisionSnapshot($lines, $source['lines']);
            $hasVariance = collect($snapshot)->contains(fn (array $line): bool => (float) $line['variance_qty'] !== 0.0);
            $revisionNumber = $count->revision_number + 1;

            InventoryCycleCountRevision::query()->create([
                'cycle_count_id' => $count->id,
                'revision_number' => $revisionNumber,
                'revision_status' => $hasVariance ? 'pending_approval' : 'completed',
                'submitted_by' => $lockedActor->id,
                'snapshot_hash' => $this->snapshotHash($snapshot),
                'lines_snapshot' => $snapshot,
                'submitted_at' => now(),
            ]);

            $count->revision_number = $revisionNumber;
            $count->status = $hasVariance ? InventoryCycleCount::STATUS_PENDING_APPROVAL : InventoryCycleCount::STATUS_COMPLETED;
            $count->submitted_by = $lockedActor->id;
            $count->submitted_at = now();
            $count->save();
            if (! $hasVariance) {
                $revision = InventoryCycleCountRevision::query()->where('cycle_count_id', $count->id)
                    ->where('revision_number', $revisionNumber)->firstOrFail();
                $this->recordDecision($revision, $lockedActor, 'completed', null, $revision->snapshot_hash);
            }

            return $count->fresh();
        });
    }

    public function decide(User $actor, int $cycleCountId, bool $approve, ?string $reason = null): InventoryCycleCount
    {
        $this->assertAuthenticatedActor($actor);
        if (! $approve && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tuliskan alasan penolakan.']);
        }

        $result = DB::transaction(function () use ($actor, $cycleCountId, $approve, $reason): array {
            $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->sharedLock()->value('mode');
            if ($mode !== InventoryMutationMode::ACTIVE) {
                throw new AuthorizationException('Persetujuan hanya tersedia saat perubahan stok aktif.');
            }
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->firstOrFail();
            $users = User::query()->whereIn('id', collect([$actor->id, $count->submitted_by])->filter()->unique()->sort()->values())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedActor = $users->get($actor->id);
            if (! $lockedActor?->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, ['inventori.view', 'inventori.stocktake.approve'])) {
                throw new AuthorizationException('Anda tidak memiliki izin untuk menyetujui hasil pemeriksaan stok.');
            }
            if ($count->submitted_by === $lockedActor->id) {
                throw new AuthorizationException('Anda tidak dapat menyetujui atau menolak hasil hitung yang Anda kirim sendiri.');
            }
            $count = InventoryCycleCount::query()->whereKey($cycleCountId)->lockForUpdate()->firstOrFail();
            if ($count->status !== InventoryCycleCount::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['cycle_count' => 'Hasil pemeriksaan ini tidak sedang menunggu persetujuan.']);
            }
            $revision = InventoryCycleCountRevision::query()->where('cycle_count_id', $count->id)
                ->where('revision_number', $count->revision_number)->lockForUpdate()->firstOrFail();
            $source = $this->baselineService->approvalSnapshotFor($lockedActor);
            $lines = $count->lines()->orderBy('item_id')->orderByRaw('lot_id NULLS FIRST')->orderBy('location_id')->lockForUpdate()->get();
            $this->authorizeViewLines($lockedActor, $lines);
            try {
                $currentSnapshot = $this->buildRevisionSnapshot($lines, $source['lines']);
            } catch (ValidationException) {
                $this->recordDecision($revision, $lockedActor, 'recount', 'Data barang atau jumlah stok berubah. Pemeriksaan ulang diperlukan.', null);
                $this->resetToDraft($count, $lines);

                return [$count->fresh(), true];
            }
            if (! hash_equals($revision->snapshot_hash, $this->snapshotHash($currentSnapshot))) {
                $this->recordDecision($revision, $lockedActor, 'recount', 'Data berubah sejak hasil dikirim. Pilih kembali barang terdampak dan hitung ulang.', null);
                $this->resetToDraft($count, $lines);

                return [$count->fresh(), true];
            }

            if (! $approve) {
                $this->recordDecision($revision, $lockedActor, 'rejected', trim((string) $reason), null);
                $this->resetToDraft($count, $lines);

                return [$count->fresh(), false];
            }

            if (collect($currentSnapshot)->contains(fn (array $line): bool => (float) $line['physical_qty'] < (float) $line['reserved_qty_snapshot'])) {
                $this->recordDecision($revision, $lockedActor, 'recount', 'Jumlah fisik lebih kecil dari jumlah barang yang dipesan. Periksa pesanan atau hitung ulang.', null);
                $this->resetToDraft($count, $lines);

                return [$count->fresh(), true];
            }

            $this->applyVariances($lockedActor, $count, $lines, $currentSnapshot);
            $resultingSource = $this->baselineService->approvalSnapshotFor($lockedActor);
            $selectedResultLines = collect($currentSnapshot)->map(function (array $revisionLine) use ($resultingSource): array {
                $source = collect($resultingSource['lines'])->first(fn (array $candidate): bool => $this->tupleKey(
                    (int) $candidate['item_id'],
                    $candidate['lot_id'] === null ? null : (int) $candidate['lot_id'],
                    (int) $candidate['location_id'],
                ) === $this->tupleKey($revisionLine['item_id'], $revisionLine['lot_id'], $revisionLine['location_id']));
                if (! $source
                    || (float) $source['system_on_hand_qty'] !== (float) $revisionLine['physical_qty']
                    || (float) $source['ledger_on_hand_qty'] !== (float) $revisionLine['physical_qty']) {
                    throw ValidationException::withMessages(['cycle_count' => 'Jumlah setelah perubahan tidak cocok dengan hasil hitung. Tidak ada perubahan yang disimpan.']);
                }

                return [
                    'item_id' => $revisionLine['item_id'],
                    'lot_id' => $revisionLine['lot_id'],
                    'location_id' => $revisionLine['location_id'],
                    'system_on_hand_qty' => $source['system_on_hand_qty'],
                    'ledger_on_hand_qty' => $source['ledger_on_hand_qty'],
                    'balance_existed' => $source['balance_existed'],
                ];
            })->all();
            $resultingFingerprint = $this->snapshotHash($selectedResultLines);
            $this->recordDecision($revision, $lockedActor, 'approved', $reason !== null ? trim($reason) : null, $resultingFingerprint);
            $count->status = InventoryCycleCount::STATUS_APPROVED;
            $count->save();

            return [$count->fresh(), false];
        });

        if ($result[1]) {
            throw ValidationException::withMessages(['cycle_count' => 'Data berubah setelah hasil dikirim. Hasil lama disimpan, dan pemeriksaan dibuka kembali untuk dihitung ulang.']);
        }

        return $result[0];
    }

    private function applyVariances(User $approver, InventoryCycleCount $count, Collection $lines, array $snapshot): void
    {
        $snapshotById = collect($snapshot)->keyBy('line_id');
        foreach ($lines as $line) {
            $row = $snapshotById->get($line->id);
            if (! $row) {
                throw ValidationException::withMessages(['cycle_count' => 'Daftar barang berubah. Muat ulang pemeriksaan sebelum melanjutkan.']);
            }
            $physicalQty = (float) $line->physical_qty;
            $systemQty = (float) $line->system_on_hand_qty;
            $reservedQty = (float) $line->reserved_qty_snapshot;
            if ($physicalQty < $reservedQty) {
                throw ValidationException::withMessages(['cycle_count' => 'Jumlah fisik tidak boleh lebih kecil dari jumlah barang yang sudah dipesan.']);
            }
            $delta = round($physicalQty - $systemQty, 3);
            if ($delta === 0.0) {
                continue;
            }

            $location = InventoryLocation::query()->whereKey($line->location_id)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedTransaction($approver, [$location->id]);
            $item = InventoryItem::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
            if ($line->lot_id !== null) {
                InventoryLot::query()->whereKey($line->lot_id)->lockForUpdate()->firstOrFail();
            }
            $balance = InventoryBalance::query()
                ->where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->when($line->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $line->lot_id))
                ->lockForUpdate()
                ->first();
            if ((float) ($balance?->on_hand_qty ?? 0) !== $systemQty
                || (float) ($balance?->reserved_qty ?? 0) !== $reservedQty
                || (bool) $line->balance_existed !== ($balance !== null)) {
                throw ValidationException::withMessages(['cycle_count' => 'Jumlah stok berubah sejak barang diperiksa. Persetujuan dibatalkan, silakan hitung ulang.']);
            }
            if (! $balance) {
                $balance = InventoryBalance::query()->create([
                    'item_id' => $line->item_id,
                    'lot_id' => $line->lot_id,
                    'location_id' => $line->location_id,
                    'on_hand_qty' => 0,
                    'reserved_qty' => 0,
                    'updated_at' => now(),
                ]);
            }
            InventoryMovement::query()->create([
                'movement_type' => 'ADJUST',
                'reference_type' => 'STOCKTAKE',
                'reference_id' => $count->id,
                'item_id' => $line->item_id,
                'lot_id' => $line->lot_id,
                'from_location_id' => $delta < 0 ? $line->location_id : null,
                'to_location_id' => $delta > 0 ? $line->location_id : null,
                'qty' => abs($delta),
                'uom' => $item->uom,
                'performed_by' => $approver->id,
                'performed_at' => now(),
                'reason_code' => 'CORRECTION',
                'notes' => 'Cycle count '.$count->id.' revision '.$count->revision_number.': '.trim((string) $line->variance_reason),
            ]);
            $balance->on_hand_qty = $physicalQty;
            $balance->updated_at = now();
            $balance->save();
        }
    }

    private function buildRevisionSnapshot(Collection $lines, array $sourceLines): array
    {
        $sourceByTuple = collect($sourceLines)->keyBy(fn (array $source): string => $this->tupleKey($source['item_id'], $source['lot_id'], $source['location_id']));

        return $lines->map(function (InventoryCycleCountLine $line) use ($sourceByTuple): array {
            $source = $sourceByTuple->get($this->tupleKey((int) $line->item_id, $line->lot_id === null ? null : (int) $line->lot_id, (int) $line->location_id));
            if (! $source || ! hash_equals($line->source_tuple_fingerprint, $this->tupleFingerprint($source))) {
                throw ValidationException::withMessages(['cycle_count' => 'Data barang atau jumlah stok berubah sejak dipilih. Hapus dan tambahkan kembali sebelum melanjutkan.']);
            }
            $balanceRows = DB::table('inventory_balances')
                ->where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->when($line->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $line->lot_id))
                ->count();
            if ($balanceRows > 1) {
                throw ValidationException::withMessages(['cycle_count' => 'Ada lebih dari satu catatan stok untuk barang dan lokasi ini. Periksa catatan tersebut sebelum melanjutkan.']);
            }
            $physicalQty = $line->physical_qty === null ? null : number_format((float) $line->physical_qty, 3, '.', '');
            $systemQty = number_format((float) $line->system_on_hand_qty, 3, '.', '');
            $variance = $physicalQty === null ? null : number_format((float) $physicalQty - (float) $systemQty, 3, '.', '');
            if ($variance !== null && (float) $variance !== 0.0 && trim((string) $line->variance_reason) === '') {
                throw ValidationException::withMessages(['cycle_count' => 'Tuliskan alasan untuk setiap barang yang jumlah fisiknya berbeda dari catatan.']);
            }

            return [
                'line_id' => $line->id,
                'item_id' => (int) $line->item_id,
                'lot_id' => $line->lot_id === null ? null : (int) $line->lot_id,
                'location_id' => (int) $line->location_id,
                'source_tuple_fingerprint' => $line->source_tuple_fingerprint,
                'system_on_hand_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'reserved_qty_snapshot' => number_format((float) $line->reserved_qty_snapshot, 3, '.', ''),
                'balance_existed' => (bool) $line->balance_existed,
                'variance_qty' => $variance,
                'variance_reason' => $line->variance_reason,
            ];
        })->all();
    }

    private function sourceSnapshotAttributes(array $sourceLine): array
    {
        return [
            'item_code_snapshot' => $sourceLine['item_code_snapshot'],
            'item_name_snapshot' => $sourceLine['item_name_snapshot'],
            'item_type_snapshot' => $sourceLine['item_type_snapshot'],
            'uom_snapshot' => $sourceLine['uom_snapshot'],
            'lot_no_snapshot' => $sourceLine['lot_no_snapshot'],
            'location_name_snapshot' => $sourceLine['location_name_snapshot'],
            'location_type_snapshot' => $sourceLine['location_type_snapshot'],
            'system_on_hand_qty' => $sourceLine['system_on_hand_qty'],
            'ledger_on_hand_qty' => $sourceLine['ledger_on_hand_qty'],
            'reserved_qty_snapshot' => $sourceLine['reserved_qty_snapshot'],
            'balance_existed' => $sourceLine['balance_existed'],
            'source_tuple_fingerprint' => $this->tupleFingerprint($sourceLine),
        ];
    }

    private function sourceLineFor(array $sourceLines, InventoryCycleCountLine $line): ?array
    {
        $key = $this->tupleKey((int) $line->item_id, $line->lot_id === null ? null : (int) $line->lot_id, (int) $line->location_id);

        return collect($sourceLines)->first(fn (array $source): bool => $this->tupleKey($source['item_id'], $source['lot_id'], $source['location_id']) === $key);
    }

    private function tupleFingerprint(array $sourceLine): string
    {
        $identity = [
            'item_id' => (int) $sourceLine['item_id'],
            'lot_id' => $sourceLine['lot_id'] === null ? null : (int) $sourceLine['lot_id'],
            'location_id' => (int) $sourceLine['location_id'],
            'item_code' => $sourceLine['item_code_snapshot'],
            'item_name' => $sourceLine['item_name_snapshot'],
            'item_type' => $sourceLine['item_type_snapshot'],
            'uom' => $sourceLine['uom_snapshot'],
            'lot_no' => $sourceLine['lot_no_snapshot'],
            'location_name' => $sourceLine['location_name_snapshot'],
            'location_type' => $sourceLine['location_type_snapshot'],
            'system_on_hand_qty' => number_format((float) $sourceLine['system_on_hand_qty'], 3, '.', ''),
            'ledger_on_hand_qty' => number_format((float) $sourceLine['ledger_on_hand_qty'], 3, '.', ''),
            'reserved_qty_snapshot' => number_format((float) $sourceLine['reserved_qty_snapshot'], 3, '.', ''),
            'balance_existed' => (bool) $sourceLine['balance_existed'],
        ];

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function authorizeViewLines(User $actor, Collection $lines): void
    {
        foreach ($lines->pluck('location_id')->unique()->sort() as $locationId) {
            $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($actor, $location);
        }
    }

    private function recordDecision(InventoryCycleCountRevision $revision, User $actor, string $decision, ?string $reason, ?string $resultingSourceFingerprint): void
    {
        InventoryCycleCountDecision::query()->create([
            'revision_id' => $revision->id,
            'actor_user_id' => $actor->id,
            'decision' => $decision,
            'reason' => $reason,
            'resulting_source_fingerprint' => $resultingSourceFingerprint,
            'decided_at' => now(),
        ]);
    }

    private function resetToDraft(InventoryCycleCount $count, Collection $lines): void
    {
        foreach ($lines as $line) {
            $line->physical_qty = null;
            $line->variance_reason = null;
            $line->counted_by = null;
            $line->counted_at = null;
            $line->save();
        }
        $count->status = InventoryCycleCount::STATUS_DRAFT;
        $count->counted_count = 0;
        $count->submitted_by = null;
        $count->submitted_at = null;
        $count->save();
    }

    private function assertTupleIntegrity(array $sourceLine): void
    {
        $policy = InventoryLotTrackingPolicy::query()->where('item_type', $sourceLine['item_type_snapshot'])->first();
        if (! $policy || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)) {
            throw ValidationException::withMessages(['tuple' => 'Aturan penggunaan nomor lot untuk jenis barang ini belum disetujui.']);
        }
        if (abs((float) $sourceLine['system_on_hand_qty'] - (float) $sourceLine['ledger_on_hand_qty']) >= 0.001) {
            throw ValidationException::withMessages(['tuple' => 'Jumlah stok tidak cocok dengan riwayat perubahannya. Periksa catatan stok terlebih dahulu.']);
        }

        $balanceCount = DB::table('inventory_balances')
            ->where('item_id', $sourceLine['item_id'])
            ->where('location_id', $sourceLine['location_id'])
            ->when($sourceLine['lot_id'] === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $sourceLine['lot_id']))
            ->count();
        if ($balanceCount > 1) {
            throw ValidationException::withMessages(['tuple' => 'Ada lebih dari satu catatan stok untuk barang dan lokasi ini, jadi belum dapat diperiksa.']);
        }
    }

    private function assertEditableBy(InventoryCycleCount $count, User $actor): void
    {
        if ($count->status !== InventoryCycleCount::STATUS_DRAFT || $count->created_by !== $actor->id) {
            throw ValidationException::withMessages(['cycle_count' => 'Pemeriksaan ini bukan draf Anda atau sudah dikirim, jadi tidak dapat diubah.']);
        }
    }

    private function authorizeActor(User $actor, array $permissions): User
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Cycle-count operations require an active database transaction.');
        }
        $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->sharedLock()->value('mode');
        if ($mode !== InventoryMutationMode::ACTIVE) {
            throw new AuthorizationException('Pemeriksaan stok hanya tersedia saat perubahan stok aktif.');
        }
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, $permissions)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk memeriksa stok.');
        }

        return $lockedActor;
    }

    private function tupleKey(int $itemId, ?int $lotId, int $locationId): string
    {
        return $itemId.'|'.($lotId === null ? 'null' : (string) $lotId).'|'.$locationId;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor siklus hitung tidak sesuai dengan pengguna yang terautentikasi.');
        }
    }
}
