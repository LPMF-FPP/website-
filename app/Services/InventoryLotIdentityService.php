<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryLot;
use Illuminate\Database\QueryException;

class InventoryLotIdentityService
{
    public static function canonicalize(string $lotNumber): string
    {
        return InventoryLot::canonicalLotNumber($lotNumber);
    }

    public function findForItem(int $itemId, string $lotNumber): ?InventoryLot
    {
        return InventoryLot::query()
            ->where('item_id', $itemId)
            ->whereRaw(InventoryLot::canonicalLotNumberSql().' = ?', [self::canonicalize($lotNumber)])
            ->first();
    }

    public function duplicateLotGuidance(int $itemId, string $lotNumber): string
    {
        $collision = $this->findForItem($itemId, $lotNumber);
        if ($collision === null) {
            return 'Nomor lot ini sudah terdaftar untuk item tersebut. Periksa lot yang sudah ada sebelum mencoba kembali.';
        }

        if ($collision->status === 'ACTIVE') {
            return "Nomor lot ini sudah terdaftar sebagai lot #{$collision->id}. Pilih lot yang sudah ada.";
        }

        return "Nomor lot ini sudah terdaftar sebagai lot #{$collision->id} dengan status {$collision->status_label}; lot tersebut tidak menerima stok baru dan tidak akan diaktifkan kembali. Hubungi pengelola inventori untuk menyelesaikannya.";
    }

    public function isIdentityConflict(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'inventory_lots_item_canonical_no_unique');
    }
}
