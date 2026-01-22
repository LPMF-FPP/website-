<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\InventoryBalance;
// Assuming this model exists
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;

class StockTransactionCommand
{
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

        $item = InventoryItem::where('name', 'LIKE', "%{$itemName}%")
            ->where('is_active', true)
            ->first();

        if (! $item) {
            return "⚠️ Barang '{$itemName}' tidak ditemukan.";
        }

        // Phone number for logging
        // $phone = explode('@', $fromJid)[0];

        try {
            DB::beginTransaction();

            $locationId = 1;

            $balance = InventoryBalance::firstOrCreate(
                ['item_id' => $item->id, 'location_id' => $locationId],
                ['on_hand_qty' => 0]
            );

            if ($isIn) {
                $balance->increment('on_hand_qty', $qty);
                $action = 'Penerimaan';
            } else {
                if ($balance->on_hand_qty < $qty) {
                    return "⚠️ Stok tidak cukup. Sisa: {$balance->on_hand_qty}";
                }
                $balance->decrement('on_hand_qty', $qty);
                $action = 'Pengeluaran';
            }

            DB::commit();

            return "✅ Transaksi Berhasil.\nItem: {$item->name}\nAction: {$action}\nJumlah: {$qty}\nSisa Stok: {$balance->on_hand_qty}";

        } catch (\Exception $e) {
            DB::rollBack();

            return '❌ Gagal memproses transaksi: '.$e->getMessage();
        }
    }
}
