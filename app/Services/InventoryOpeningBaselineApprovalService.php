<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\InventoryOpeningBaseline;
use App\Models\InventoryOpeningBaselineApproval;
use App\Models\InventoryOpeningBaselineLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOpeningBaselineApprovalService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryOpeningBaselineService $baselineService,
    ) {}

    public function submit(User $actor, int $baselineId): InventoryOpeningBaseline
    {
        $this->assertAuthenticatedActor($actor);

        $result = DB::transaction(function () use ($actor, $baselineId): InventoryOpeningBaseline {
            $this->lockOpeningMode();
            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->firstOrFail();
            [$lockedActor, $manifestOwner] = $this->lockUsers($actor, (int) $baseline->generated_by, ['inventori.view', 'inventori.stocktake.submit']);
            $source = $this->baselineService->approvalSnapshotFor($manifestOwner);
            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->lockForUpdate()->firstOrFail();
            if ($this->assertCompleteAndCurrent($baseline, $source) === null) {
                return $baseline->fresh();
            }
            if (! in_array($baseline->status, [InventoryOpeningBaseline::STATUS_DRAFT], true)) {
                throw ValidationException::withMessages(['baseline' => 'Hanya daftar yang sudah dihitung ulang yang dapat dikirim.']);
            }

            $baseline->submission_revision++;
            $baseline->status = InventoryOpeningBaseline::STATUS_SUBMITTED;
            $baseline->submitted_by = $lockedActor->id;
            $baseline->submitted_at = now();
            $baseline->save();

            return $baseline->fresh();
        });

        if ($result->status === InventoryOpeningBaseline::STATUS_STALE) {
            throw ValidationException::withMessages(['baseline' => 'Data barang, izin akses, jumlah stok, atau riwayatnya berubah. Buat daftar stok awal baru dan hitung ulang.']);
        }

        return $result;
    }

    public function decide(User $actor, int $baselineId, bool $approve, ?string $note = null): InventoryOpeningBaseline
    {
        $this->assertAuthenticatedActor($actor);
        if (! $approve && trim((string) $note) === '') {
            throw ValidationException::withMessages(['decision_note' => 'Tuliskan catatan jika meminta hitung ulang.']);
        }

        $result = DB::transaction(function () use ($actor, $baselineId, $approve, $note): InventoryOpeningBaseline {
            $this->lockOpeningMode();
            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->firstOrFail();
            [$lockedActor, $manifestOwner] = $this->lockUsers($actor, (int) $baseline->generated_by, ['inventori.view', 'inventori.stocktake.approve']);
            $source = $this->baselineService->approvalSnapshotFor($manifestOwner);
            $baseline = InventoryOpeningBaseline::query()->whereKey($baselineId)->lockForUpdate()->firstOrFail();
            if ($baseline->status !== InventoryOpeningBaseline::STATUS_SUBMITTED || $baseline->submitted_by === null) {
                throw ValidationException::withMessages(['baseline' => 'Hasil hitung belum dikirim untuk diperiksa.']);
            }
            if ($baseline->submitted_by === $lockedActor->id) {
                throw new AuthorizationException('Anda tidak dapat menyetujui hasil hitung yang Anda kirim sendiri.');
            }

            $source = $this->assertCompleteAndCurrent($baseline, $source);
            if ($source === null) {
                return $baseline->fresh();
            }
            $lines = $baseline->lines()->orderBy('item_id')->orderByRaw('lot_id NULLS FIRST')->orderBy('location_id')->lockForUpdate()->get();
            $this->authorizeVisibleLines($lockedActor, $lines);
            $approvedSnapshot = $this->buildApprovedSnapshot($lines, $source['lines'], $approve);

            if ($approve) {
                $this->applyVariances($lockedActor, $lines, $baseline);
            }
            $postDecisionFingerprint = null;
            if ($approve) {
                $postDecisionSource = $this->baselineService->approvalSnapshotFor($manifestOwner);
                if ($postDecisionSource['blockers'] !== []
                    || ! hash_equals($source['catalog_fingerprint'], $postDecisionSource['catalog_fingerprint'])) {
                    throw ValidationException::withMessages(['baseline' => 'Jumlah setelah perubahan tidak cocok dengan riwayat stok. Tidak ada perubahan yang disimpan.']);
                }
                $postDecisionFingerprint = $postDecisionSource['fingerprint'];
            }

            InventoryOpeningBaselineApproval::query()->create([
                'baseline_id' => $baseline->id,
                'submission_revision' => $baseline->submission_revision,
                'submitted_by' => $baseline->submitted_by,
                'approved_by' => $lockedActor->id,
                'decision' => $approve ? 'approved' : 'returned',
                'decision_note' => $note !== null ? trim($note) : null,
                'source_fingerprint' => $source['fingerprint'],
                'resulting_source_fingerprint' => $postDecisionFingerprint,
                'approved_lines_snapshot' => $approvedSnapshot,
                'decided_at' => now(),
            ]);

            if ($approve) {
                $baseline->status = InventoryOpeningBaseline::STATUS_APPROVED;
                $baseline->approved_by = $lockedActor->id;
                $baseline->approved_at = now();
                $baseline->save();

                return $baseline->fresh();
            }

            $baseline->lines()->update([
                'physical_qty' => null,
                'counted_by' => null,
                'counted_at' => null,
            ]);
            $baseline->status = InventoryOpeningBaseline::STATUS_DRAFT;
            $baseline->counted_count = 0;
            $baseline->submitted_by = null;
            $baseline->submitted_at = null;
            $baseline->save();

            return $baseline->fresh();
        });

        if ($result->status === InventoryOpeningBaseline::STATUS_STALE) {
            throw ValidationException::withMessages(['baseline' => 'Data barang, izin akses, jumlah stok, atau riwayatnya berubah. Buat daftar stok awal baru dan hitung ulang.']);
        }

        return $result;
    }

    private function assertCompleteAndCurrent(InventoryOpeningBaseline $baseline, array $source): ?array
    {
        if ($baseline->status === InventoryOpeningBaseline::STATUS_STALE) {
            throw ValidationException::withMessages(['baseline' => 'Daftar sudah tidak sesuai dengan data terbaru. Buat daftar baru dan hitung ulang.']);
        }
        if (($baseline->blockers ?? []) !== [] || $baseline->tuple_count < 1) {
            throw ValidationException::withMessages(['baseline' => 'Daftar belum lengkap atau tidak memiliki barang untuk dihitung.']);
        }

        $lines = $baseline->lines()->orderBy('item_id')->orderByRaw('lot_id NULLS FIRST')->orderBy('location_id')->get();
        if ($lines->count() !== $baseline->tuple_count
            || $baseline->counted_count !== $baseline->tuple_count
            || $lines->contains(fn (InventoryOpeningBaselineLine $line): bool => $line->physical_qty === null)) {
            throw ValidationException::withMessages(['baseline' => 'Hitung semua barang yang ada di daftar sebelum mengirimnya untuk diperiksa.']);
        }

        if (! hash_equals($baseline->source_fingerprint, $source['fingerprint'])) {
            if ($baseline->status !== InventoryOpeningBaseline::STATUS_STALE) {
                $baseline->status = InventoryOpeningBaseline::STATUS_STALE;
                $baseline->stale_at = now();
                $baseline->blockers = [...($baseline->blockers ?? []), 'manifest_stale'];
                $baseline->save();
            }

            return null;
        }

        return $source;
    }

    private function buildApprovedSnapshot(Collection $lines, array $sourceLines, bool $requireReservableCounts): array
    {
        $sourceByTuple = collect($sourceLines)->keyBy(fn (array $line): string => $this->tupleKey($line['item_id'], $line['lot_id'], $line['location_id']));
        $seenTuples = [];

        return $lines->map(function (InventoryOpeningBaselineLine $line) use ($sourceByTuple, $requireReservableCounts, &$seenTuples): array {
            $key = $this->tupleKey((int) $line->item_id, $line->lot_id === null ? null : (int) $line->lot_id, (int) $line->location_id);
            $current = $sourceByTuple->get($key);
            if (isset($seenTuples[$key])) {
                throw ValidationException::withMessages(['baseline' => 'Daftar berisi barang yang sama lebih dari sekali. Tidak ada perubahan stok yang diterapkan.']);
            }
            $seenTuples[$key] = true;
            if (! $current
                || (float) $line->system_on_hand_qty !== (float) $current['system_on_hand_qty']
                || (float) $line->reserved_qty_snapshot !== (float) $current['reserved_qty_snapshot']
                || (bool) $line->balance_existed !== (bool) $current['balance_existed']) {
                throw ValidationException::withMessages(['baseline' => 'Daftar berbeda dari data barang saat ini. Hitung ulang sebelum melanjutkan.']);
            }
            if ($requireReservableCounts && (float) $line->physical_qty < (float) $line->reserved_qty_snapshot) {
                throw ValidationException::withMessages(['baseline' => 'Jumlah fisik tidak boleh lebih kecil dari jumlah barang yang sudah dipesan.']);
            }
            $delta = round((float) $line->physical_qty - (float) $line->system_on_hand_qty, 3);

            return [
                'line_id' => $line->id,
                'item_id' => $line->item_id,
                'lot_id' => $line->lot_id,
                'location_id' => $line->location_id,
                'system_on_hand_qty' => (string) $line->system_on_hand_qty,
                'physical_qty' => (string) $line->physical_qty,
                'reserved_qty_snapshot' => (string) $line->reserved_qty_snapshot,
                'balance_existed' => (bool) $line->balance_existed,
                'variance_qty' => number_format($delta, 3, '.', ''),
            ];
        })->all();
    }

    private function applyVariances(User $approver, Collection $lines, InventoryOpeningBaseline $baseline): void
    {
        foreach ($lines as $line) {
            $location = InventoryLocation::query()->whereKey($line->location_id)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($approver, $location);
            $item = InventoryItem::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
            if ($line->lot_id !== null) {
                InventoryLot::query()->whereKey($line->lot_id)->lockForUpdate()->firstOrFail();
            }

            $balanceQuery = InventoryBalance::query()
                ->where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->when($line->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $line->lot_id))
                ->lockForUpdate();
            $balance = $balanceQuery->first();
            $currentOnHand = (float) ($balance?->on_hand_qty ?? 0);
            $currentReserved = (float) ($balance?->reserved_qty ?? 0);
            if ((float) $line->system_on_hand_qty !== $currentOnHand
                || (float) $line->reserved_qty_snapshot !== $currentReserved
                || (bool) $line->balance_existed !== ($balance !== null)) {
                throw ValidationException::withMessages(['baseline' => 'Jumlah stok berubah saat pemeriksaan. Tidak ada perubahan yang diterapkan. Periksa dan kirim ulang hasil hitung.']);
            }

            $physicalQty = (float) $line->physical_qty;
            if ($physicalQty < $currentReserved) {
                throw ValidationException::withMessages(['baseline' => 'Jumlah fisik tidak boleh lebih kecil dari jumlah barang yang sudah dipesan.']);
            }
            $delta = round($physicalQty - $currentOnHand, 3);
            if ($delta === 0.0) {
                continue;
            }
            $this->locationAccess->authorizeLockedTransaction($approver, [$location->id]);

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
                'reference_id' => $baseline->id,
                'item_id' => $line->item_id,
                'lot_id' => $line->lot_id,
                'from_location_id' => $delta < 0 ? $line->location_id : null,
                'to_location_id' => $delta > 0 ? $line->location_id : null,
                'qty' => abs($delta),
                'uom' => $item->uom,
                'performed_by' => $approver->id,
                'performed_at' => now(),
                'reason_code' => 'CORRECTION',
                'notes' => 'Persetujuan baseline '.$baseline->id.' revisi '.$baseline->submission_revision,
            ]);

            $balance->on_hand_qty = $physicalQty;
            $balance->updated_at = now();
            $balance->save();
        }
    }

    private function authorizeVisibleLines(User $approver, Collection $lines): void
    {
        foreach ($lines->pluck('location_id')->unique()->sort() as $locationId) {
            $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
            $this->locationAccess->authorizeLockedView($approver, $location);
        }
    }

    private function lockUsers(User $actor, int $manifestOwnerId, array $permissions): array
    {
        $users = User::query()->whereIn('id', collect([$actor->id, $manifestOwnerId])->unique()->sort()->values())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedActor = $users->get($actor->id);
        $manifestOwner = $users->get($manifestOwnerId);
        if (! $lockedActor?->is_active || ! $manifestOwner) {
            throw new AuthorizationException('Akun pemohon atau pembuat baseline tidak lagi aktif/tersedia.');
        }
        if (! $this->permissionService->userHasPermissionsForMutation($lockedActor, $permissions)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk proses baseline ini.');
        }

        return [$lockedActor, $manifestOwner];
    }

    private function lockOpeningMode(): void
    {
        $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->lockForUpdate()->value('mode');
        if ($mode !== InventoryMutationMode::OPENING_BASELINE) {
            throw new AuthorizationException('Persetujuan baseline hanya tersedia saat mode opening_baseline aktif.');
        }
    }

    private function tupleKey(int $itemId, ?int $lotId, int $locationId): string
    {
        return $itemId.'|'.($lotId === null ? 'null' : (string) $lotId).'|'.$locationId;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor baseline tidak sesuai dengan pengguna yang terautentikasi.');
        }
    }
}
