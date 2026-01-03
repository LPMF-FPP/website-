<?php

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Verifying Changes...\n";

// 1. Verify Constant
$types = InventoryMovement::REFERENCE_TYPES;
if (! isset($types['CHANGELOG'])) {
    echo "FAIL: CHANGELOG key not found in InventoryMovement::REFERENCE_TYPES\n";
    exit(1);
}
if ($types['CHANGELOG'] !== 'Changelogs') {
    echo "FAIL: CHANGELOG label mismatch. Expected 'Changelogs', got '{$types['CHANGELOG']}'\n";
    exit(1);
}
echo "PASS: Constant InventoryMovement::REFERENCE_TYPES updated correctly.\n";

// 2. Verify Database Persistence (Simulation)
// We will attempt to create a movement using the service to ensure no DB constraints fail.
try {
    DB::beginTransaction();

    // Create Dummy Data
    $item = InventoryItem::first();
    if (! $item) {
        $item = InventoryItem::factory()->create(['name' => 'Test Item', 'uom' => 'pcs']);
    }

    $location = InventoryLocation::first();
    if (! $location) {
        $location = InventoryLocation::factory()->create(['name' => 'Test Location']);
    }

    $service = app(InventoryMovementService::class);

    echo "Attempting to record receipt with reference_type = 'CHANGELOG'...\n";

    // We don't save to DB to avoid polluting, but running the method validates the logic
    // Actually, to test DB constraint we MUST save. We will rollback transaction.

    $service->receipt([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'qty' => 10,
        'uom' => $item->uom,
        'reference_type' => 'CHANGELOG',
        'unit_cost' => 1000,
        'notes' => 'Verification Test',
    ]);

    // Check if it was saved
    $movement = InventoryMovement::where('reference_type', 'CHANGELOG')
        ->where('notes', 'Verification Test')
        ->first();

    if ($movement) {
        echo "PASS: Successfully saved InventoryMovement with reference_type = 'CHANGELOG'.\n";
    } else {
        echo "FAIL: Movement Record not found in DB after save.\n";
        exit(1);
    }

    DB::rollBack();
    echo "Transaction rolled back (cleanup).\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo 'FAIL: Exception thrown during movement creation: '.$e->getMessage()."\n";
    exit(1);
}
