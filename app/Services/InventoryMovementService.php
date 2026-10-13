<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryMovementService
{
    public function __construct(
        private readonly InventoryMutationGate $mutationGate,
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryLotIdentityService $lotIdentity,
        private readonly InventoryStockCardService $stockCards,
    ) {}

    /**
     * Create a RECEIPT movement (incoming stock).
     */
    public function receipt(array $data, ?User $actor = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $lotMode = $data['lot_mode'] ?? null;
            $requiredPermissions = ['inventori.receipt.create'];
            if ($lotMode === 'new') {
                $requiredPermissions[] = 'inventori.create';
            }
            $actorId = $this->authorizeMutation($data, $actor, $requiredPermissions);
            $quantity = $data['qty'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity < 0.001 || (float) $quantity > 99999999999.999
                || abs(((float) $quantity * 1000) - round((float) $quantity * 1000)) > 0.000001) {
                throw new RuntimeException('Jumlah penerimaan harus positif dan maksimal tiga angka desimal.');
            }
            $unitCost = $data['unit_cost'] ?? null;
            if ($unitCost !== null && (! is_numeric($unitCost) || (float) $unitCost < 0 || (float) $unitCost > 999999999999.99
                || abs(((float) $unitCost * 100) - round((float) $unitCost * 100)) > 0.000001)) {
                throw new RuntimeException('Harga satuan tidak valid.');
            }
            if (! isset($data['item_id'], $data['location_id']) || (int) $data['item_id'] < 1 || (int) $data['location_id'] < 1) {
                throw new RuntimeException('Item dan lokasi penerimaan wajib dipilih.');
            }
            $referenceType = $data['reference_type'] ?? 'MANUAL';
            if (! is_string($referenceType) || ! array_key_exists($referenceType, InventoryMovement::REFERENCE_TYPES)) {
                throw new RuntimeException('Tipe referensi penerimaan tidak dikenal.');
            }
            $this->lockLocations([$data['location_id'] ?? null]);
            $lockedActor = User::query()->findOrFail($actorId);
            $this->locationAccess->authorizeLockedTransaction($lockedActor, [(int) $data['location_id']]);
            $item = $this->lockItem((int) $data['item_id']);
            if (! $this->locationAccess->itemHasTransactionAccess($lockedActor, $item->id, (int) $data['location_id'], allowUnassociated: true)) {
                throw new AuthorizationException('Item tidak tersedia dalam cakupan transaksi lokasi ini.');
            }
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->lockForUpdate()->first();
            if (! $policy
                || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                || trim((string) $policy->evidence_reference) === ''
                || $policy->decided_by_user_id === null
                || $policy->decided_at === null) {
                throw new RuntimeException('Kebijakan lot untuk tipe item ini belum disetujui.');
            }
            $lotId = isset($data['lot_id']) ? (int) $data['lot_id'] : null;
            $lot = null;
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::UNTRACKED) {
                if ($lotMode !== 'untracked' || $lotId !== null || ! empty($data['new_lot_no']) || ! empty($data['new_lot_expiry'])) {
                    throw new RuntimeException('Item ini untracked. Pilih mode untracked dan jangan kirim lot.');
                }
            } elseif ($lotMode === 'existing') {
                if ($lotId === null || ! empty($data['new_lot_no']) || ! empty($data['new_lot_expiry'])) {
                    throw new RuntimeException('Pilih satu lot aktif yang sudah ada dan kosongkan bidang lot baru.');
                }
                $lot = $this->lockLotForItem($lotId, $item->id);
            } elseif ($lotMode === 'new') {
                $newLotNumber = (string) ($data['new_lot_no'] ?? '');
                $canonicalNewLotNumber = InventoryLotIdentityService::canonicalize($newLotNumber);
                if ($lotId !== null || $canonicalNewLotNumber === '' || mb_strlen($newLotNumber) > 100) {
                    throw new RuntimeException('Masukkan nomor lot baru tanpa memilih lot yang sudah ada.');
                }
                if ($item->requiresExpiry() && empty($data['new_lot_expiry'])) {
                    throw new RuntimeException('Tanggal kadaluarsa wajib diisi untuk jenis item ini.');
                }
                if (! empty($data['new_lot_expiry']) && strtotime((string) $data['new_lot_expiry']) === false) {
                    throw new RuntimeException('Tanggal kadaluarsa lot tidak valid.');
                }
                if (! empty($data['new_lot_expiry']) && strtotime((string) $data['new_lot_expiry']) < strtotime(now()->toDateString())) {
                    throw new RuntimeException('Tanggal kadaluarsa lot harus hari ini atau setelahnya.');
                }
                $collision = $this->lotIdentity->findForItem($item->id, $newLotNumber);
                if ($collision !== null) {
                    throw new RuntimeException($this->lotIdentity->duplicateLotGuidance($item->id, $newLotNumber));
                }
                $lot = InventoryLot::query()->create([
                    'item_id' => $item->id,
                    'lot_no' => $newLotNumber,
                    'expiry_date' => $data['new_lot_expiry'] ?? null,
                    'received_date' => now()->toDateString(),
                    'status' => 'ACTIVE',
                ]);
                $lotId = (int) $lot->id;
            } else {
                throw new RuntimeException('Pilih mode lot secara eksplisit sesuai kebijakan item.');
            }

            if ($lot && $lot->status !== 'ACTIVE') {
                throw new RuntimeException('Penerimaan hanya dapat dicatat ke lot berstatus aktif.');
            }

            if ($lot) {
                $hasLocationHistory = $lot->balances()->where('location_id', $data['location_id'])->exists()
                    || $lot->movements()->where(function ($movements) use ($data): void {
                        $movements->where('from_location_id', $data['location_id'])
                            ->orWhere('to_location_id', $data['location_id']);
                    })->exists();
                $hasAnyHistory = $lot->balances()->exists() || $lot->movements()->exists();

                if ($hasAnyHistory && ! $hasLocationHistory) {
                    throw new RuntimeException('Lot ini tidak tersedia untuk penerimaan pada lokasi yang dipilih.');
                }
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'RECEIPT',
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $lotId,
                'lot_mode' => $lotMode === 'untracked' ? 'untracked' : ($lotMode === 'new' ? 'tracked_new' : 'tracked_existing'),
                'from_location_id' => null,
                'to_location_id' => $data['location_id'],
                'qty' => $quantity,
                'uom' => $item->uom,
                'unit_cost' => $unitCost,
                'performed_by' => $actorId,
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Increase balance at target location
            $balance = InventoryBalance::findOrCreateFor(
                $data['item_id'],
                $lotId,
                $data['location_id']
            );
            $balance->increaseOnHand((float) $data['qty']);

            return $movement;
        });
    }

    /**
     * Create an ISSUE movement (outgoing stock).
     *
     * @throws RuntimeException if insufficient stock or lot is expired
     */
    public function issue(array $data, ?User $actor = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $actorId = $this->authorizeMutation($data, $actor, 'inventori.issue.create');
            $quantity = $data['qty'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity < 0.001 || (float) $quantity > 99999999999.999
                || abs(((float) $quantity * 1000) - round((float) $quantity * 1000)) > 0.000001) {
                throw new RuntimeException('Jumlah pengeluaran harus positif dan maksimal tiga angka desimal.');
            }
            $referenceType = $data['reference_type'] ?? 'MANUAL';
            if (! is_string($referenceType) || ! array_key_exists($referenceType, InventoryMovement::REFERENCE_TYPES)) {
                throw new RuntimeException('Tipe referensi pengeluaran tidak dikenal.');
            }
            if (! isset($data['item_id'], $data['location_id']) || (int) $data['item_id'] < 1 || (int) $data['location_id'] < 1) {
                throw new RuntimeException('Item dan lokasi sumber wajib dipilih.');
            }
            $this->lockLocations([$data['location_id'] ?? null]);
            $lockedActor = User::query()->findOrFail($actorId);
            $this->locationAccess->authorizeLockedTransaction($lockedActor, [(int) $data['location_id']]);
            $item = $this->lockItem((int) $data['item_id']);
            if (! $this->locationAccess->itemHasTransactionAccess($lockedActor, $item->id, (int) $data['location_id'])) {
                throw new AuthorizationException('Item tidak tersedia pada lokasi sumber yang dipilih.');
            }
            $lotId = isset($data['lot_id']) ? (int) $data['lot_id'] : null;
            $lot = $this->lockLotForItem($lotId, $item->id);
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->lockForUpdate()->first();
            if (! $policy
                || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                || trim((string) $policy->evidence_reference) === ''
                || $policy->decided_by_user_id === null
                || $policy->decided_at === null) {
                throw new RuntimeException('Kebijakan pelacakan lot belum disetujui untuk tipe item ini.');
            }
            $lotMode = $data['lot_mode'] ?? null;
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                if ($lotMode !== 'existing' || $lotId === null) {
                    throw new RuntimeException('Item lot-tracked memerlukan satu lot aktif yang dipilih secara eksplisit.');
                }
                if (! $lot->canBeIssued()) {
                    throw new RuntimeException("Lot {$lot->lot_no} tidak dapat dikeluarkan. Status: {$lot->status_label}");
                }
            } elseif ($lotMode !== 'untracked' || $lotId !== null) {
                throw new RuntimeException('Item ini untracked. Pilih mode untracked tanpa lot.');
            }

            $balanceRows = InventoryBalance::query()
                ->where('item_id', $item->id)
                ->where('location_id', $data['location_id'])
                ->when($lotId === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $lotId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($balanceRows->count() > 1) {
                throw new RuntimeException('Tuple memiliki baris saldo ganda; selesaikan rekonsiliasi sebelum pengeluaran.');
            }
            $balance = $balanceRows->first();
            $available = $balance?->available_qty ?? 0;

            if (is_numeric($data['expected_on_hand_qty'] ?? null) && is_numeric($data['expected_available_qty'] ?? null)
                && $balance
                && (abs((float) $balance->on_hand_qty - (float) $data['expected_on_hand_qty']) > 0.0005
                    || abs((float) $available - (float) $data['expected_available_qty']) > 0.0005)) {
                throw new RuntimeException('Saldo lot berubah sejak dimuat. Muat ulang konteks stok sebelum mencoba kembali.');
            }

            if (! $balance || $available < (float) $quantity) {
                throw new RuntimeException('Stok tersedia tidak mencukupi setelah reservasi. Tersedia: '.number_format((float) $available, 3).' '.$item->uom.'.');
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'ISSUE',
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $item->id,
                'lot_id' => $lotId,
                'lot_mode' => $lotMode === 'untracked' ? 'untracked' : 'tracked_existing',
                'from_location_id' => $data['location_id'],
                'to_location_id' => null,
                'qty' => $quantity,
                'uom' => $item->uom,
                'performed_by' => $actorId,
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Decrease balance at source location
            $balance->decreaseOnHand((float) $quantity);

            return $movement;
        });
    }

    /**
     * Create a TRANSFER movement (move stock between locations).
     *
     * @throws RuntimeException if insufficient stock
     */
    public function transfer(array $data, ?User $actor = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $actorId = $this->authorizeMutation($data, $actor, 'inventori.transfer.create');
            $quantity = $data['qty'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity < 0.001 || (float) $quantity > 99999999999.999
                || abs(((float) $quantity * 1000) - round((float) $quantity * 1000)) > 0.000001) {
                throw new RuntimeException('Jumlah transfer harus positif dan maksimal tiga angka desimal.');
            }
            if (! isset($data['item_id'], $data['from_location_id'], $data['to_location_id'])
                || (int) $data['item_id'] < 1 || (int) $data['from_location_id'] < 1 || (int) $data['to_location_id'] < 1) {
                throw new RuntimeException('Item, lokasi asal, dan lokasi tujuan wajib dipilih.');
            }
            $referenceType = $data['reference_type'] ?? 'MANUAL';
            if (! is_string($referenceType) || ! array_key_exists($referenceType, InventoryMovement::REFERENCE_TYPES)) {
                throw new RuntimeException('Tipe referensi transfer tidak dikenal.');
            }
            $sourceLocationId = (int) $data['from_location_id'];
            $destinationLocationId = (int) $data['to_location_id'];
            if ($sourceLocationId === $destinationLocationId) {
                throw new RuntimeException('Lokasi asal dan tujuan transfer harus berbeda.');
            }
            $this->lockLocations([$sourceLocationId, $destinationLocationId]);
            $lockedActor = User::query()->findOrFail($actorId);
            $this->locationAccess->authorizeLockedTransaction($lockedActor, [$sourceLocationId, $destinationLocationId]);
            $item = $this->lockItem((int) $data['item_id']);
            if (! $this->locationAccess->itemHasTransactionAccess($lockedActor, $item->id, $sourceLocationId)) {
                throw new AuthorizationException('Item tidak tersedia pada lokasi sumber yang dipilih.');
            }
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->lockForUpdate()->first();
            if (! $policy
                || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                || trim((string) $policy->evidence_reference) === ''
                || $policy->decided_by_user_id === null
                || $policy->decided_at === null) {
                throw new RuntimeException('Kebijakan pelacakan lot belum disetujui untuk tipe item ini.');
            }

            $lotId = isset($data['lot_id']) ? (int) $data['lot_id'] : null;
            $lotMode = $data['lot_mode'] ?? null;
            $lot = null;
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                if ($lotMode !== 'existing' || $lotId === null) {
                    throw new RuntimeException('Item lot-tracked memerlukan satu lot aktif yang dipilih secara eksplisit.');
                }
                $lot = $this->lockLotForItem($lotId, $item->id);
                if (! $lot->canBeIssued()) {
                    throw new RuntimeException("Lot {$lot->lot_no} tidak dapat ditransfer. Status: {$lot->status_label}");
                }
            } elseif ($lotMode !== 'untracked' || $lotId !== null) {
                throw new RuntimeException('Item ini untracked. Pilih mode untracked tanpa lot.');
            }

            $balanceRows = InventoryBalance::query()
                ->where('item_id', $item->id)
                ->when($lotId === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $lotId))
                ->whereIn('location_id', [$sourceLocationId, $destinationLocationId])
                ->orderBy('item_id')
                ->orderByRaw('lot_id ASC NULLS FIRST')
                ->orderBy('location_id')
                ->lockForUpdate()
                ->get();
            if ($balanceRows->groupBy('location_id')->contains(fn ($rows): bool => $rows->count() > 1)) {
                throw new RuntimeException('Tuple saldo transfer memiliki baris ganda; selesaikan rekonsiliasi sebelum transfer.');
            }
            $sourceBalance = $balanceRows->firstWhere('location_id', $sourceLocationId);
            $destinationBalance = $balanceRows->firstWhere('location_id', $destinationLocationId);
            $available = $sourceBalance?->available_qty ?? 0;

            if (is_numeric($data['expected_on_hand_qty'] ?? null) && is_numeric($data['expected_available_qty'] ?? null)
                && $sourceBalance
                && (abs((float) $sourceBalance->on_hand_qty - (float) $data['expected_on_hand_qty']) > 0.0005
                    || abs((float) $available - (float) $data['expected_available_qty']) > 0.0005)) {
                throw new RuntimeException('Saldo lokasi asal berubah sejak dimuat. Muat ulang konteks stok sebelum mencoba kembali.');
            }

            if (! $sourceBalance || $available < (float) $quantity) {
                throw new RuntimeException('Stok tersedia di lokasi asal tidak mencukupi setelah reservasi. Tersedia: '.number_format((float) $available, 3).' '.$item->uom.'.');
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'TRANSFER',
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $item->id,
                'lot_id' => $lotId,
                'from_location_id' => $sourceLocationId,
                'to_location_id' => $destinationLocationId,
                'qty' => $quantity,
                'uom' => $item->uom,
                'performed_by' => $actorId,
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $sourceBalance->decreaseOnHand((float) $quantity);
            $destinationBalance ??= InventoryBalance::findOrCreateFor($item->id, $lotId, $destinationLocationId);
            $destinationBalance->increaseOnHand((float) $quantity);

            return $movement;
        });
    }

    /**
     * Direct stock adjustments are disabled until the reviewed stocktake
     * workflow can apply an approved immutable revision.
     */
    public function adjust(array $data, ?User $actor = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor): InventoryMovement {
            $this->authorizeMutation($data, $actor, 'inventori.stocktake.submit');

            throw new AuthorizationException('Penyesuaian stok langsung dinonaktifkan; gunakan workflow stok opname yang disetujui.');
        });
    }

    /**
     * Create a DISPOSE movement.
     */
    public function dispose(array $data, ?User $actor = null): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $actorId = $this->authorizeMutation($data, $actor, 'inventori.material-disposal.create');
            $quantity = $data['qty'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity < 0.001 || (float) $quantity > 99999999999.999
                || abs(((float) $quantity * 1000) - round((float) $quantity * 1000)) > 0.000001) {
                throw new RuntimeException('Jumlah disposal harus positif dan maksimal tiga angka desimal.');
            }
            if (! isset($data['item_id'], $data['location_id']) || (int) $data['item_id'] < 1 || (int) $data['location_id'] < 1) {
                throw new RuntimeException('Item dan lokasi sumber disposal wajib dipilih.');
            }
            $reasonCode = $data['reason_code'] ?? null;
            if (! is_string($reasonCode) || ! array_key_exists($reasonCode, InventoryMovement::REASON_CODES)) {
                throw new RuntimeException('Pilih alasan disposal yang terdaftar.');
            }
            if (! in_array($data['review_confirmed'] ?? null, [true, 1, '1', 'yes', 'on'], true)) {
                throw new RuntimeException('Tinjau dan konfirmasi detail disposal sebelum mencatatnya.');
            }
            $referenceType = $data['reference_type'] ?? 'DISPOSAL_DOC';
            if (! is_string($referenceType) || ! array_key_exists($referenceType, InventoryMovement::REFERENCE_TYPES)) {
                throw new RuntimeException('Tipe referensi disposal tidak dikenal.');
            }

            $this->lockLocations([$data['location_id'] ?? null]);
            $lockedActor = User::query()->findOrFail($actorId);
            $this->locationAccess->authorizeLockedTransaction($lockedActor, [(int) $data['location_id']]);
            $item = $this->lockItem((int) $data['item_id']);
            if (! $this->locationAccess->itemHasTransactionAccess($lockedActor, $item->id, (int) $data['location_id'])) {
                throw new AuthorizationException('Item tidak tersedia pada lokasi sumber disposal yang dipilih.');
            }
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->lockForUpdate()->first();
            if (! $policy
                || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                || trim((string) $policy->evidence_reference) === ''
                || $policy->decided_by_user_id === null
                || $policy->decided_at === null) {
                throw new RuntimeException('Kebijakan pelacakan lot belum disetujui untuk tipe item ini.');
            }

            $lotId = isset($data['lot_id']) ? (int) $data['lot_id'] : null;
            $lotMode = $data['lot_mode'] ?? null;
            $lot = null;
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                if ($lotMode !== 'existing' || $lotId === null) {
                    throw new RuntimeException('Item lot-tracked memerlukan satu lot yang dipilih secara eksplisit untuk disposal.');
                }
                $lot = $this->lockLotForItem($lotId, $item->id);
                if ($lot->status === 'DISPOSED') {
                    throw new RuntimeException('Lot yang telah didisposal tidak dapat diproses kembali.');
                }
            } elseif ($lotMode !== 'untracked' || $lotId !== null) {
                throw new RuntimeException('Item ini untracked. Pilih mode untracked tanpa lot.');
            }

            $balanceRows = InventoryBalance::query()
                ->where('item_id', $item->id)
                ->when($lotId === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $lotId))
                ->where('location_id', $data['location_id'])
                ->orderBy('item_id')
                ->orderByRaw('lot_id ASC NULLS FIRST')
                ->orderBy('location_id')
                ->lockForUpdate()
                ->get();
            if ($balanceRows->count() > 1) {
                throw new RuntimeException('Tuple saldo disposal memiliki baris ganda; selesaikan rekonsiliasi sebelum disposal.');
            }
            $balance = $balanceRows->first();
            $available = $balance?->available_qty ?? 0;

            if (is_numeric($data['expected_on_hand_qty'] ?? null) && is_numeric($data['expected_available_qty'] ?? null)
                && $balance
                && (abs((float) $balance->on_hand_qty - (float) $data['expected_on_hand_qty']) > 0.0005
                    || abs((float) $available - (float) $data['expected_available_qty']) > 0.0005)) {
                throw new RuntimeException('Saldo disposal berubah sejak ditinjau. Muat ulang konteks dan tinjau ulang sebelum mencoba kembali.');
            }

            if (! $balance || $available < (float) $quantity) {
                throw new RuntimeException('Stok tersedia tidak mencukupi untuk disposal setelah reservasi. Tersedia: '.number_format((float) $available, 3).' '.$item->uom.'.');
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'DISPOSE',
                'reference_type' => $referenceType,
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $item->id,
                'lot_id' => $lotId,
                'from_location_id' => $data['location_id'],
                'to_location_id' => null,
                'qty' => $quantity,
                'uom' => $item->uom,
                'performed_by' => $actorId,
                'performed_at' => $data['performed_at'] ?? now(),
                'reason_code' => $reasonCode,
                'notes' => $data['notes'] ?? null,
            ]);

            $balance->decreaseOnHand((float) $quantity);

            if ($lot !== null) {
                $remainingBalance = InventoryBalance::query()->where('lot_id', $lot->id)
                    ->sum('on_hand_qty');
                if ($remainingBalance <= 0) {
                    $lot->update(['status' => 'DISPOSED']);
                }
            }

            return $movement;
        });
    }

    /**
     * Disabled compatibility entry point; it never applies legacy stocktake rows.
     *
     * @param  array  $countedItems  Legacy count rows retained for call compatibility only.
     * @return array No summary is produced because stocktake processing is disabled.
     */
    public function processStocktake(array $countedItems, ?int $performedBy = null, ?User $actor = null): array
    {
        return DB::transaction(function () use ($performedBy, $actor): array {
            $this->authorizeMutation(['performed_by' => $performedBy], $actor, 'inventori.stocktake.submit');

            throw new AuthorizationException('Opname langsung dinonaktifkan sampai workflow draft dan persetujuan independen tersedia.');
        });
    }

    private function authorizeMutation(array $data, ?User $actor, string|array $permissionNames): int
    {
        $authenticatedActor = auth()->user();

        if ($authenticatedActor && $actor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor mutasi tidak sesuai dengan pengguna yang terautentikasi.');
        }

        $actor ??= $authenticatedActor;
        $reportedActorId = $data['performed_by'] ?? null;

        if (! $actor || ($reportedActorId !== null && (int) $reportedActorId !== $actor->id)) {
            throw new AuthorizationException('Aktor mutasi inventori tidak dapat diverifikasi.');
        }

        $this->mutationGate->authorize($actor->id, $permissionNames);

        return $actor->id;
    }

    private function lockItem(int $itemId): InventoryItem
    {
        $item = InventoryItem::query()
            ->whereKey($itemId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $item->is_active) {
            throw new RuntimeException('Item sudah diarsipkan dan tidak dapat digunakan untuk mutasi baru.');
        }

        return $item;
    }

    /**
     * Lock and validate locations in ascending ID order before locking stock items.
     *
     * @param  list<int|string|null>  $locationIds
     */
    private function lockLocations(array $locationIds): void
    {
        $ids = collect($locationIds)
            ->filter(fn ($id): bool => $id !== null && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->count() !== count($locationIds)) {
            throw new RuntimeException('Pilih lokasi inventori yang valid.');
        }

        $locations = InventoryLocation::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($locations->count() !== $ids->count()) {
            throw new RuntimeException('Lokasi inventori tidak ditemukan.');
        }
    }

    private function lockLotForItem(?int $lotId, int $itemId): ?InventoryLot
    {
        if ($lotId === null) {
            return null;
        }

        $lot = InventoryLot::query()
            ->whereKey($lotId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($lot->item_id !== $itemId) {
            throw new RuntimeException('Lot yang dipilih bukan milik item ini.');
        }

        return $lot;
    }

    /**
     * Get stock card movements with running balance.
     */
    public function getStockCard(array $filters, User $viewer): array
    {
        return $this->stockCards->report($filters, $viewer)['rows'];
    }
}
