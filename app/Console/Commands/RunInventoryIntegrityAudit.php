<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryIntegrityAuditLine;
use App\Services\InventoryIntegrityAuditService;
use Illuminate\Console\Command;
use RuntimeException;

class RunInventoryIntegrityAudit extends Command
{
    protected $signature = 'inventory:integrity-audit';

    protected $description = 'Record a repeatable-read audit of inventory ledger and balance projections';

    public function handle(InventoryIntegrityAuditService $auditService): int
    {
        try {
            $audit = $auditService->run();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Inventory integrity audit #{$audit->id}: {$audit->status}; snapshot={$audit->snapshot_id}; tuples={$audit->tuple_count}; drift={$audit->drift_count}.");

        if ($audit->drift_count === 0) {
            return self::SUCCESS;
        }

        $lines = InventoryIntegrityAuditLine::query()
            ->where('audit_id', $audit->id)
            ->orderBy('item_id')
            ->orderByRaw('lot_id ASC NULLS FIRST')
            ->orderBy('location_id')
            ->limit(50)
            ->get();
        $this->table(
            ['Item', 'Lot', 'Location', 'Ledger', 'Balance', 'Delta'],
            $lines->map(fn (InventoryIntegrityAuditLine $line): array => [
                $line->item_id,
                $line->lot_id ?? 'null',
                $line->location_id,
                $line->ledger_qty,
                $line->balance_qty,
                $line->delta_qty,
            ])->all(),
        );
        $this->error('Drift dicatat sebagai bukti audit. Tidak ada saldo atau ledger yang diubah.');

        return self::FAILURE;
    }
}
