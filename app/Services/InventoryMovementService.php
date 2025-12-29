<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryMovementService
{
    /**
     * Create a RECEIPT movement (incoming stock).
     */
    public function receipt(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $movement = InventoryMovement::create([
                'movement_type' => 'RECEIPT',
                'reference_type' => $data['reference_type'] ?? 'MANUAL',
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'from_location_id' => null,
                'to_location_id' => $data['location_id'],
                'qty' => $data['qty'],
                'uom' => $data['uom'],
                'unit_cost' => $data['unit_cost'] ?? null,
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Increase balance at target location
            $balance = InventoryBalance::findOrCreateFor(
                $data['item_id'],
                $data['lot_id'] ?? null,
                $data['location_id']
            );
            $balance->increaseOnHand((float) $data['qty']);

            return $movement;
        });
    }

    /**
     * Create an ISSUE movement (outgoing stock).
     * @throws RuntimeException if insufficient stock or lot is expired
     */
    public function issue(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Validate lot if provided
            if (!empty($data['lot_id'])) {
                $lot = InventoryLot::findOrFail($data['lot_id']);
                if (!$lot->canBeIssued()) {
                    throw new RuntimeException(
                        "Lot {$lot->lot_no} tidak dapat dikeluarkan. Status: {$lot->status_label}"
                    );
                }
            }

            // Check balance
            $balance = InventoryBalance::where([
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'location_id' => $data['location_id'],
            ])->first();

            if (!$balance || $balance->on_hand_qty < $data['qty']) {
                $available = $balance ? $balance->on_hand_qty : 0;
                throw new RuntimeException(
                    "Stok tidak mencukupi. Tersedia: {$available}, Diminta: {$data['qty']}"
                );
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'ISSUE',
                'reference_type' => $data['reference_type'] ?? 'MANUAL',
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'from_location_id' => $data['location_id'],
                'to_location_id' => null,
                'qty' => $data['qty'],
                'uom' => $data['uom'],
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Decrease balance at source location
            $balance->decreaseOnHand((float) $data['qty']);

            return $movement;
        });
    }

    /**
     * Create a TRANSFER movement (move stock between locations).
     * @throws RuntimeException if insufficient stock
     */
    public function transfer(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Check source balance
            $sourceBalance = InventoryBalance::where([
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'location_id' => $data['from_location_id'],
            ])->first();

            if (!$sourceBalance || $sourceBalance->on_hand_qty < $data['qty']) {
                $available = $sourceBalance ? $sourceBalance->on_hand_qty : 0;
                throw new RuntimeException(
                    "Stok tidak mencukupi di lokasi asal. Tersedia: {$available}, Diminta: {$data['qty']}"
                );
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'TRANSFER',
                'reference_type' => $data['reference_type'] ?? 'MANUAL',
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'from_location_id' => $data['from_location_id'],
                'to_location_id' => $data['to_location_id'],
                'qty' => $data['qty'],
                'uom' => $data['uom'],
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Decrease source balance
            $sourceBalance->decreaseOnHand((float) $data['qty']);

            // Increase destination balance
            $destBalance = InventoryBalance::findOrCreateFor(
                $data['item_id'],
                $data['lot_id'] ?? null,
                $data['to_location_id']
            );
            $destBalance->increaseOnHand((float) $data['qty']);

            return $movement;
        });
    }

    /**
     * Create an ADJUST movement (stocktake correction).
     */
    public function adjust(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            $qty = (float) $data['qty'];
            $isIncrease = $qty > 0;

            $movement = InventoryMovement::create([
                'movement_type' => 'ADJUST',
                'reference_type' => $data['reference_type'] ?? 'STOCKTAKE',
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'from_location_id' => $isIncrease ? null : $data['location_id'],
                'to_location_id' => $isIncrease ? $data['location_id'] : null,
                'qty' => abs($qty),
                'uom' => $data['uom'],
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
                'reason_code' => $data['reason_code'] ?? 'CORRECTION',
                'notes' => $data['notes'] ?? null,
            ]);

            // Update balance
            $balance = InventoryBalance::findOrCreateFor(
                $data['item_id'],
                $data['lot_id'] ?? null,
                $data['location_id']
            );

            if ($isIncrease) {
                $balance->increaseOnHand(abs($qty));
            } else {
                $balance->decreaseOnHand(abs($qty), true); // Allow negative for adjustments
            }

            return $movement;
        });
    }

    /**
     * Create a DISPOSE movement.
     */
    public function dispose(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Check balance
            $balance = InventoryBalance::where([
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'location_id' => $data['location_id'],
            ])->first();

            if (!$balance || $balance->on_hand_qty < $data['qty']) {
                $available = $balance ? $balance->on_hand_qty : 0;
                throw new RuntimeException(
                    "Stok tidak mencukupi untuk disposal. Tersedia: {$available}, Diminta: {$data['qty']}"
                );
            }

            $movement = InventoryMovement::create([
                'movement_type' => 'DISPOSE',
                'reference_type' => $data['reference_type'] ?? 'DISPOSAL_DOC',
                'reference_id' => $data['reference_id'] ?? null,
                'item_id' => $data['item_id'],
                'lot_id' => $data['lot_id'] ?? null,
                'from_location_id' => $data['location_id'],
                'to_location_id' => null,
                'qty' => $data['qty'],
                'uom' => $data['uom'],
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'performed_at' => $data['performed_at'] ?? now(),
                'reason_code' => $data['reason_code'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Decrease balance
            $balance->decreaseOnHand((float) $data['qty']);

            // Update lot status if disposing entire lot
            if (!empty($data['lot_id'])) {
                $lot = InventoryLot::find($data['lot_id']);
                $remainingBalance = InventoryBalance::where('lot_id', $data['lot_id'])
                    ->sum('on_hand_qty');
                if ($remainingBalance <= 0) {
                    $lot->update(['status' => 'DISPOSED']);
                }
            }

            return $movement;
        });
    }

    /**
     * Process stocktake results and create ADJUST movements for variances.
     * @param array $countedItems Array of ['item_id', 'lot_id', 'location_id', 'counted_qty']
     * @return array Summary of adjustments made
     */
    public function processStocktake(array $countedItems, ?int $performedBy = null): array
    {
        $results = [];

        DB::transaction(function () use ($countedItems, $performedBy, &$results) {
            foreach ($countedItems as $row) {
                $balance = InventoryBalance::where([
                    'item_id' => $row['item_id'],
                    'lot_id' => $row['lot_id'] ?? null,
                    'location_id' => $row['location_id'],
                ])->first();

                $systemQty = $balance ? (float) $balance->on_hand_qty : 0;
                $countedQty = (float) $row['counted_qty'];
                $variance = $countedQty - $systemQty;

                if (abs($variance) < 0.001) {
                    // No variance
                    $results[] = [
                        'item_id' => $row['item_id'],
                        'lot_id' => $row['lot_id'] ?? null,
                        'location_id' => $row['location_id'],
                        'system_qty' => $systemQty,
                        'counted_qty' => $countedQty,
                        'variance' => 0,
                        'adjusted' => false,
                    ];
                    continue;
                }

                $item = InventoryItem::find($row['item_id']);

                // Create adjustment
                $this->adjust([
                    'item_id' => $row['item_id'],
                    'lot_id' => $row['lot_id'] ?? null,
                    'location_id' => $row['location_id'],
                    'qty' => $variance,
                    'uom' => $item->uom,
                    'reference_type' => 'STOCKTAKE',
                    'reason_code' => 'CORRECTION',
                    'notes' => "Stock opname: sistem={$systemQty}, fisik={$countedQty}",
                    'performed_by' => $performedBy ?? auth()->id(),
                ]);

                $results[] = [
                    'item_id' => $row['item_id'],
                    'lot_id' => $row['lot_id'] ?? null,
                    'location_id' => $row['location_id'],
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'variance' => $variance,
                    'adjusted' => true,
                ];
            }
        });

        return $results;
    }

    /**
     * Get stock card movements with running balance.
     */
    public function getStockCard(array $filters): array
    {
        $query = InventoryMovement::query()
            ->with(['item', 'lot', 'fromLocation', 'toLocation', 'performedByUser'])
            ->orderBy('performed_at', 'asc')
            ->orderBy('id', 'asc');

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }
        if (!empty($filters['lot_id'])) {
            $query->where('lot_id', $filters['lot_id']);
        }
        if (!empty($filters['location_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('from_location_id', $filters['location_id'])
                  ->orWhere('to_location_id', $filters['location_id']);
            });
        }
        if (!empty($filters['date_from'])) {
            $query->where('performed_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('performed_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $movements = $query->get();

        // Calculate running balance for specific location
        $locationId = $filters['location_id'] ?? null;
        $runningBalance = 0;

        // Get opening balance if location is specified
        if ($locationId && !empty($filters['date_from'])) {
            $openingBalance = InventoryMovement::query()
                ->where('performed_at', '<', $filters['date_from']);
            
            if (!empty($filters['item_id'])) {
                $openingBalance->where('item_id', $filters['item_id']);
            }
            if (!empty($filters['lot_id'])) {
                $openingBalance->where('lot_id', $filters['lot_id']);
            }

            // Sum incoming movements
            $incoming = (clone $openingBalance)
                ->where('to_location_id', $locationId)
                ->sum('qty');
            
            // Sum outgoing movements
            $outgoing = (clone $openingBalance)
                ->where('from_location_id', $locationId)
                ->sum('qty');

            $runningBalance = (float) $incoming - (float) $outgoing;
        }

        $result = [];
        foreach ($movements as $movement) {
            $change = 0;
            
            if ($locationId) {
                // Calculate change for specific location
                if ($movement->to_location_id == $locationId) {
                    $change = (float) $movement->qty;
                }
                if ($movement->from_location_id == $locationId) {
                    $change -= (float) $movement->qty;
                }
            } else {
                // No location filter - show signed qty
                $change = $movement->signed_qty;
            }

            $runningBalance += $change;

            $result[] = [
                'movement' => $movement,
                'change' => $change,
                'running_balance' => $runningBalance,
            ];
        }

        return $result;
    }
}
