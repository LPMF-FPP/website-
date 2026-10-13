<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryIntegrityAudit;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\User;
use App\Services\InventoryIntegrityAuditService;
use App\Services\InventoryMovementService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryPostgreSqlIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsInventoryReservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pgsql', DB::getDriverName(), 'PostgreSQL is required to verify database integrity guarantees.');
    }

    public function test_database_rejects_movement_update_delete_and_truncate(): void
    {
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $movement = InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 4,
            'uom' => $item->uom,
        ]);

        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_movements')->where('id', $movement->id)->update(['qty' => 9]));
        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_movements')->where('id', $movement->id)->delete());
        $this->assertDatabaseMutationRejected(fn () => DB::statement('TRUNCATE TABLE inventory_movements'));

        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'qty' => 4,
            'movement_type' => 'RECEIPT',
        ]);
    }

    public function test_database_rejects_nonpositive_quantities_malformed_endpoints_and_new_returns(): void
    {
        $item = InventoryItem::factory()->create();
        $source = InventoryLocation::factory()->create(['is_restricted' => false]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        $base = [
            'item_id' => $item->id,
            'qty' => 1,
            'uom' => $item->uom,
        ];

        foreach ([
            ['movement_type' => 'RECEIPT', 'from_location_id' => null, 'to_location_id' => $destination->id, 'qty' => 0],
            ['movement_type' => 'RECEIPT', 'from_location_id' => $source->id, 'to_location_id' => $destination->id],
            ['movement_type' => 'ISSUE', 'from_location_id' => null, 'to_location_id' => null],
            ['movement_type' => 'DISPOSE', 'from_location_id' => null, 'to_location_id' => $destination->id],
            ['movement_type' => 'TRANSFER', 'from_location_id' => $source->id, 'to_location_id' => $source->id],
            ['movement_type' => 'ADJUST', 'from_location_id' => null, 'to_location_id' => null],
            ['movement_type' => 'ADJUST', 'from_location_id' => $source->id, 'to_location_id' => $destination->id],
            ['movement_type' => 'RETURN', 'from_location_id' => null, 'to_location_id' => $destination->id],
        ] as $invalidMovement) {
            $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_movements')->insert(array_merge($base, $invalidMovement)));
        }

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_database_enforces_null_and_nonnull_tuple_uniqueness_and_balance_quantity_bounds(): void
    {
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);

        $nullTuple = ['item_id' => $item->id, 'lot_id' => null, 'location_id' => $location->id, 'on_hand_qty' => 1, 'reserved_qty' => 0];
        $lotTuple = ['item_id' => $item->id, 'lot_id' => $lot->id, 'location_id' => $location->id, 'on_hand_qty' => 1, 'reserved_qty' => 0];
        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_balances')->insert($nullTuple));
        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_balances')->insert($lotTuple));
        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_balances')->insert([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => -1,
            'reserved_qty' => 0,
        ]));
        $this->assertDatabaseMutationRejected(fn () => $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 2,
        ]));

        $this->assertDatabaseCount('inventory_balances', 2);
    }

    public function test_database_rejects_reserved_quantity_inserts_and_updates(): void
    {
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $balance = InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);

        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_balances')->where('id', $balance->id)->update(['reserved_qty' => 1]));
        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_balances')->insert([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 1,
        ]));
        $this->assertSame(0.0, (float) $balance->fresh()->reserved_qty);
        $this->assertDatabaseCount('inventory_balances', 1);
    }

    public function test_migration_preflight_reports_legacy_null_lot_duplicates_without_rewriting_them(): void
    {
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        DB::statement('DROP INDEX inventory_balances_null_lot_tuple_unique');
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);

        $migration = require database_path('migrations/2026_10_06_000001_enforce_inventory_postgresql_integrity.php');
        try {
            $migration->up();
            $this->fail('Migration preflight must block duplicate null-lot tuples.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate null-lot tuples', $exception->getMessage());
        }

        $this->assertSame(2, InventoryBalance::query()->where('item_id', $item->id)->count());
    }

    public function test_integrity_audit_records_a_repeatable_read_drift_without_repairing_stock(): void
    {
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 5,
            'uom' => $item->uom,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);
        $movementCount = InventoryMovement::query()->count();
        $balanceQty = (float) InventoryBalance::query()->value('on_hand_qty');

        $audit = app(InventoryIntegrityAuditService::class)->run();

        $this->assertSame('drift_detected', $audit->status);
        $this->assertNotEmpty($audit->snapshot_id);
        $this->assertSame(1, $audit->tuple_count);
        $this->assertSame(1, $audit->drift_count);
        $line = $audit->lines()->sole();
        $this->assertSame($item->id, $line->item_id);
        $this->assertSame($location->id, $line->location_id);
        $this->assertTrue($line->balance_existed);
        $this->assertEquals(5, (float) $line->ledger_qty);
        $this->assertEquals(4, (float) $line->balance_qty);
        $this->assertEquals(-1, (float) $line->delta_qty);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame($balanceQty, (float) InventoryBalance::query()->value('on_hand_qty'));

        $this->assertDatabaseMutationRejected(fn () => DB::table('inventory_integrity_audit_lines')->where('id', $line->id)->update(['delta_qty' => 0]));
        $this->assertEquals(-1, (float) $line->fresh()->delta_qty);
    }

    public function test_integrity_audit_command_records_a_clean_snapshot_and_does_not_change_stock(): void
    {
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 3,
            'uom' => $item->uom,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);

        $this->artisan('inventory:integrity-audit')
            ->expectsOutputToContain(': clean; snapshot=')
            ->assertExitCode(0);

        $audit = InventoryIntegrityAudit::query()->latest('id')->firstOrFail();
        $this->assertSame('clean', $audit->status);
        $this->assertSame(1, $audit->tuple_count);
        $this->assertSame(0, $audit->drift_count);
        $this->assertSame(0, $audit->lines()->count());
        $this->assertSame(1, InventoryMovement::query()->where('item_id', $item->id)->count());
        $this->assertSame(3.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
    }

    public function test_integrity_audit_command_reports_and_persists_drift_without_repairing(): void
    {
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 8,
            'uom' => $item->uom,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 6,
            'reserved_qty' => 0,
        ]);

        $this->artisan('inventory:integrity-audit')
            ->expectsOutputToContain(': drift_detected; snapshot=')
            ->expectsOutputToContain('Drift dicatat sebagai bukti audit')
            ->assertExitCode(1);

        $audit = InventoryIntegrityAudit::query()->latest('id')->firstOrFail();
        $this->assertSame('drift_detected', $audit->status);
        $this->assertSame(1, $audit->drift_count);
        $this->assertSame(1, $audit->lines()->count());
        $this->assertSame(1, InventoryMovement::query()->where('item_id', $item->id)->count());
        $this->assertSame(6.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
    }

    public function test_postgresql_balance_trigger_failure_rolls_back_service_movement_and_projection(): void
    {
        $this->seed(PermissionSeeder::class);
        $policyFixtureActor = User::factory()->create(['role' => 'penyelia', 'is_active' => false]);
        DB::table('inventory_lot_tracking_policies')->where('item_type', 'OTHER')->update([
            'lot_tracking_mode' => InventoryLotTrackingPolicy::UNTRACKED,
            'evidence_reference' => 'TEST-INTEGRITY-APPROVED',
            'decided_by_user_id' => $policyFixtureActor->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fail_inventory_balance_projection_for_test() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'simulated projection failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fail_inventory_balance_projection_for_test
                BEFORE UPDATE ON inventory_balances
                FOR EACH ROW EXECUTE FUNCTION fail_inventory_balance_projection_for_test();
            SQL);

        try {
            app(InventoryMovementService::class)->issue([
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $location->id,
                'qty' => 2,
            ], $user);
            $this->fail('PostgreSQL projection trigger should abort the whole movement transaction.');
        } catch (QueryException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertSame(5.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_inventory_balance_projection_for_test ON inventory_balances');
            DB::unprepared('DROP FUNCTION IF EXISTS fail_inventory_balance_projection_for_test()');
        }
    }

    private function assertDatabaseMutationRejected(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
            $this->fail('PostgreSQL invariant trigger or constraint should reject the write.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }
}
