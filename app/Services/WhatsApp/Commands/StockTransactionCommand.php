<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\WhatsApp\WhitelistService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockTransactionCommand
{
    public function __construct(
        private InventoryMovementService $movementService,
        private WhitelistService $whitelistService,
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        // If no params (or just "/stok"), list items
        if (empty($params)) {
            $items = InventoryItem::where('is_active', true)
                ->withSum('balances', 'on_hand_qty')
                ->orderBy('name')
                ->take(15) // Limit to avoid spam
                ->get();

            if ($items->isEmpty()) {
                return "📦 *Data Stok Kosong*\nBelum ada item terdaftar.";
            }

            $response = "📦 *DAFTAR STOK (Top 15)*\n\n";
            foreach ($items as $item) {
                $stok = $item->balances_sum_on_hand_qty ?? 0;
                $response .= "• {$item->name}: {$stok} {$item->uom}\n";
            }
            $response .= "\nKetik `/stok masuk untracked {nama} {jml}` atau `/stok keluar untracked {nama} {jml}` untuk item tanpa lot.";

            return $response;
        }

        $type = strtolower($params[0]);
        if (! in_array($type, ['masuk', 'keluar', 'in', 'out'])) {
            return "⚠️ Tipe transaksi harus 'masuk' atau 'keluar'.";
        }

        $isIn = in_array($type, ['masuk', 'in']);
        if ($isIn) {
            if (count($params) < 4 || strtolower($params[1]) !== 'untracked') {
                return 'Penerimaan lewat WhatsApp hanya tersedia dengan mode lot eksplisit `untracked`. Gunakan: /stok masuk untracked {nama_barang} {jumlah}. Untuk lot-tracked, gunakan formulir Penerimaan Stok.';
            }
            $itemName = $params[2];
            $qty = floatval($params[3]);
        } else {
            if (count($params) < 4 || strtolower($params[1]) !== 'untracked') {
                return 'Pengeluaran lewat WhatsApp hanya tersedia dengan mode lot eksplisit `untracked`. Gunakan: /stok keluar untracked {nama_barang} {jumlah}. Untuk lot-tracked, gunakan formulir Pengeluaran Stok.';
            }
            $itemName = $params[2];
            $qty = floatval($params[3]);
        }

        $needle = strtolower($itemName);

        $item = InventoryItem::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])
            ->where('is_active', true)
            ->first();

        if (! $item) {
            return "⚠️ Barang '{$itemName}' tidak ditemukan.";
        }

        $fromPhone = $this->whitelistService->normalizePhoneNumber($fromJid);
        $performedBy = $this->resolvePerformedByUserId($fromPhone);
        $actor = $performedBy === null ? null : User::query()->find($performedBy);

        if (! $actor || ! $actor->is_active) {
            return '⚠️ Akun aplikasi untuk nomor ini tidak aktif atau tidak dapat diverifikasi.';
        }

        $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
        if (! $policy
            || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
            || trim((string) $policy->evidence_reference) === ''
            || $policy->decided_by_user_id === null
            || $policy->decided_at === null) {
            return 'Kebijakan lot item belum disetujui; hubungi administrator inventori.';
        }
        if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
            return $isIn
                ? 'Penerimaan lot-tracked harus memakai formulir Penerimaan Stok agar lot dipilih secara eksplisit.'
                : 'Pengeluaran lot-tracked harus memakai formulir Pengeluaran Stok agar satu lot dipilih secara eksplisit.';
        }

        try {
            $locationId = $this->resolveLocationId($item->id, $isIn);
            if (! $locationId) {
                return '⚠️ Tidak ada lokasi inventori yang terdaftar. Tambahkan lokasi terlebih dahulu.';
            }

            $notes = "WhatsApp: {$fromPhone}";

            if ($isIn) {
                $movement = $this->movementService->receipt([
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'lot_mode' => 'untracked',
                    'location_id' => $locationId,
                    'qty' => $qty,
                    'uom' => $item->uom,
                    'reference_type' => 'MANUAL',
                    'performed_by' => $actor->id,
                    'notes' => $notes,
                ], $actor);
                $action = 'Penerimaan';
            } else {
                $movement = $this->movementService->issue([
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'lot_mode' => 'untracked',
                    'location_id' => $locationId,
                    'qty' => $qty,
                    'uom' => $item->uom,
                    'reference_type' => 'MANUAL',
                    'performed_by' => $actor->id,
                    'notes' => $notes,
                ], $actor);
                $action = 'Pengeluaran';
            }

            $balance = DB::table('inventory_balances')
                ->where('item_id', $item->id)
                ->whereNull('lot_id')
                ->where('location_id', $locationId)
                ->value('on_hand_qty');

            $balanceText = $balance === null ? '0' : (string) ((float) $balance);

            return "✅ Transaksi Berhasil.\nItem: {$item->name}\nAction: {$action}\nJumlah: {$qty} {$item->uom}\nLokasi ID: {$locationId}\nSisa Stok: {$balanceText}";
        } catch (RuntimeException $e) {
            return '⚠️ '.$e->getMessage();
        } catch (\Throwable $e) {
            return '❌ Gagal memproses transaksi: '.$e->getMessage();
        }
    }

    private function resolveLocationId(int $itemId, bool $isIn): ?int
    {
        $defaultLocationId = InventoryLocation::query()->orderBy('id')->value('id');

        if ($isIn) {
            return $defaultLocationId;
        }

        $bestStockLocationId = DB::table('inventory_balances')
            ->where('item_id', $itemId)
            ->whereNull('lot_id')
            ->where('on_hand_qty', '>', 0)
            ->orderByDesc('on_hand_qty')
            ->value('location_id');

        return $bestStockLocationId ?: $defaultLocationId;
    }

    private function resolvePerformedByUserId(string $normalizedE164): ?int
    {
        $local = null;
        $noPrefix = null;

        if (str_starts_with($normalizedE164, '62')) {
            $noPrefix = substr($normalizedE164, 2);
            $local = '0'.$noPrefix;
        }

        return User::query()
            ->whereIn('phone', array_values(array_filter([
                $normalizedE164,
                $local,
                $noPrefix,
            ])))
            ->value('id');
    }
}
