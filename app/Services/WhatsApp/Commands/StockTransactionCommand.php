<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
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
            $response .= "\nKetik `/stok {masuk/keluar} {nama} {jml}` untuk transaksi.";

            return $response;
        }

        if (count($params) < 3) {
            return "⚠️ Format salah.\nGunakan: /stok {masuk/keluar} {nama_barang} {jumlah}\nContoh: /stok masuk alkohol 5";
        }

        $type = strtolower($params[0]);
        $itemName = $params[1];
        $qty = floatval($params[2]);

        if (! in_array($type, ['masuk', 'keluar', 'in', 'out'])) {
            return "⚠️ Tipe transaksi harus 'masuk' atau 'keluar'.";
        }

        $isIn = in_array($type, ['masuk', 'in']);

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
                    'location_id' => $locationId,
                    'qty' => $qty,
                    'uom' => $item->uom,
                    'reference_type' => 'MANUAL',
                    'performed_by' => $performedBy,
                    'notes' => $notes,
                ]);
                $action = 'Penerimaan';
            } else {
                $movement = $this->movementService->issue([
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'location_id' => $locationId,
                    'qty' => $qty,
                    'uom' => $item->uom,
                    'reference_type' => 'MANUAL',
                    'performed_by' => $performedBy,
                    'notes' => $notes,
                ]);
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
