<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreflightInventoryLotIdentity extends Command
{
    protected $signature = 'inventory:lots:preflight-identity';

    protected $description = 'Read-only report of item-scoped inventory lot identity collisions';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Preflight identitas lot hanya dapat membuktikan constraint pada PostgreSQL.');

            return self::FAILURE;
        }

        $issues = DB::table('inventory_lots')
            ->select('item_id')
            ->selectRaw("BTRIM(lot_no, ' ') AS canonical_lot_no")
            ->selectRaw('COUNT(*) AS lot_count')
            ->groupBy('item_id')
            ->groupByRaw("BTRIM(lot_no, ' ')")
            ->havingRaw("COUNT(*) > 1 OR BTRIM(lot_no, ' ') = ''")
            ->orderBy('item_id')
            ->get();
        if ($issues->isNotEmpty()) {
            $this->table(
                ['Issue', 'Item ID', 'Lot IDs', 'Entered lot numbers', 'Lot statuses', 'Canonical lot number'],
                $issues->map(function (object $issue): array {
                    $lots = DB::table('inventory_lots')
                        ->where('item_id', $issue->item_id)
                        ->whereRaw("BTRIM(lot_no, ' ') = ?", [$issue->canonical_lot_no])
                        ->orderBy('id')
                        ->get(['id', 'lot_no', 'status']);

                    return [
                        $issue->canonical_lot_no === '' ? 'Empty canonical identity' : 'Canonical collision',
                        (string) $issue->item_id,
                        $lots->pluck('id')->implode(', '),
                        $lots->pluck('lot_no')->map(fn (string $lotNumber): string => json_encode($lotNumber, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->implode(' | '),
                        $lots->pluck('status')->implode(', '),
                        $issue->canonical_lot_no,
                    ];
                })->values()->all(),
            );
            $this->error('Identitas lot kosong atau berbenturan. Tidak ada data yang diubah; selesaikan setiap record sebelum migrasi indeks unik.');

            return self::FAILURE;
        }

        $this->info('Preflight identitas lot lulus. Tidak ada perubahan data yang dilakukan.');

        return self::SUCCESS;
    }
}
