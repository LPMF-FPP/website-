<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryIntegrityAudit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryIntegrityAuditService
{
    private const TUPLES_SQL = <<<'SQL'
        WITH movement_legs AS (
            SELECT item_id, lot_id, from_location_id AS location_id, -qty AS delta_qty
            FROM inventory_movements
            WHERE from_location_id IS NOT NULL
            UNION ALL
            SELECT item_id, lot_id, to_location_id AS location_id, qty AS delta_qty
            FROM inventory_movements
            WHERE to_location_id IS NOT NULL
        ), ledger_totals AS (
            SELECT item_id, lot_id, location_id, SUM(delta_qty) AS ledger_qty
            FROM movement_legs
            GROUP BY item_id, lot_id, location_id
        )
        SELECT
            COALESCE(b.item_id, l.item_id) AS item_id,
            COALESCE(b.lot_id, l.lot_id) AS lot_id,
            COALESCE(b.location_id, l.location_id) AS location_id,
            b.id AS balance_id,
            COALESCE(l.ledger_qty, 0)::numeric(30, 3) AS ledger_qty,
            COALESCE(b.on_hand_qty, 0)::numeric(30, 3) AS balance_qty,
            (COALESCE(b.on_hand_qty, 0) - COALESCE(l.ledger_qty, 0))::numeric(30, 3) AS delta_qty
        FROM inventory_balances b
        FULL OUTER JOIN ledger_totals l
            ON b.item_id = l.item_id
            AND b.location_id = l.location_id
            AND b.lot_id IS NOT DISTINCT FROM l.lot_id
        SQL;

    public function run(): InventoryIntegrityAudit
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Audit integritas ledger inventori memerlukan PostgreSQL. SQLite tidak membuktikan invariant PostgreSQL.');
        }

        if (DB::connection()->transactionLevel() > 0) {
            $isolation = DB::selectOne('SHOW transaction_isolation')->transaction_isolation;
            if (strtolower((string) $isolation) !== 'repeatable read') {
                throw new RuntimeException('Audit integritas harus dimulai pada transaksi repeatable read yang belum membaca data inventori.');
            }

            return $this->recordAudit();
        }

        return DB::transaction(function (): InventoryIntegrityAudit {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            return $this->recordAudit();
        });
    }

    private function recordAudit(): InventoryIntegrityAudit
    {
        $snapshot = DB::selectOne('SELECT txid_current_snapshot()::text AS snapshot_id, transaction_timestamp() AS audited_at');
        $summary = DB::selectOne(<<<'SQL'
                WITH movement_legs AS (
                    SELECT item_id, lot_id, from_location_id AS location_id, -qty AS delta_qty
                    FROM inventory_movements
                    WHERE from_location_id IS NOT NULL
                    UNION ALL
                    SELECT item_id, lot_id, to_location_id AS location_id, qty AS delta_qty
                    FROM inventory_movements
                    WHERE to_location_id IS NOT NULL
                ), ledger_totals AS (
                    SELECT item_id, lot_id, location_id, SUM(delta_qty) AS ledger_qty
                    FROM movement_legs
                    GROUP BY item_id, lot_id, location_id
                ), tuples AS (
                    SELECT
                        COALESCE(b.item_id, l.item_id) AS item_id,
                        COALESCE(b.lot_id, l.lot_id) AS lot_id,
                        COALESCE(b.location_id, l.location_id) AS location_id,
                        COALESCE(l.ledger_qty, 0)::numeric(30, 3) AS ledger_qty,
                        COALESCE(b.on_hand_qty, 0)::numeric(30, 3) AS balance_qty
                    FROM inventory_balances b
                    FULL OUTER JOIN ledger_totals l
                        ON b.item_id = l.item_id
                        AND b.location_id = l.location_id
                        AND b.lot_id IS NOT DISTINCT FROM l.lot_id
                )
                SELECT COUNT(*)::bigint AS tuple_count,
                    COUNT(*) FILTER (WHERE ledger_qty <> balance_qty)::bigint AS drift_count
                FROM tuples
            SQL);

        $audit = InventoryIntegrityAudit::query()->create([
            'snapshot_id' => $snapshot->snapshot_id,
            'audited_at' => $snapshot->audited_at,
            'status' => (int) $summary->drift_count === 0 ? 'clean' : 'drift_detected',
            'tuple_count' => (int) $summary->tuple_count,
            'drift_count' => (int) $summary->drift_count,
        ]);

        if ((int) $summary->drift_count > 0) {
            $offset = 0;
            do {
                $rows = DB::select(self::TUPLES_SQL.<<<'SQL'
                        WHERE COALESCE(l.ledger_qty, 0)::numeric(30, 3) <> COALESCE(b.on_hand_qty, 0)::numeric(30, 3)
                        ORDER BY COALESCE(b.item_id, l.item_id), COALESCE(b.lot_id, l.lot_id) ASC NULLS FIRST, COALESCE(b.location_id, l.location_id)
                        LIMIT ? OFFSET ?
                    SQL, [500, $offset]);

                $createdAt = $snapshot->audited_at;
                $lines = array_map(static fn (object $row): array => [
                    'audit_id' => $audit->id,
                    'item_id' => (int) $row->item_id,
                    'lot_id' => $row->lot_id === null ? null : (int) $row->lot_id,
                    'location_id' => (int) $row->location_id,
                    'balance_existed' => $row->balance_id !== null,
                    'ledger_qty' => (string) $row->ledger_qty,
                    'balance_qty' => (string) $row->balance_qty,
                    'delta_qty' => (string) $row->delta_qty,
                    'created_at' => $createdAt,
                ], $rows);

                if ($lines !== []) {
                    DB::table('inventory_integrity_audit_lines')->insert($lines);
                }

                $offset += count($rows);
            } while (count($rows) === 500);
        }

        return $audit->fresh();
    }
}
