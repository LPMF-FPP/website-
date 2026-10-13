<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SampleDisposalStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryLotTrackingPolicyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryFeatureDemoDataSeeder extends Seeder
{
    private const MARKER = 'TEST ONLY: INVENTORY FEATURE DEMO';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'pgsql'
            || ! in_array(DB::connection()->getDatabaseName(), ['lis_db_test', 'lpmf_story_1_2_verify'], true)) {
            throw new RuntimeException('Data dummy inventori hanya boleh dibuat di lis_db_test atau lpmf_story_1_2_verify.');
        }

        $this->call(InventoryFeatureTestUsersSeeder::class);
        $operator = User::query()->where('email', InventoryFeatureTestUsersSeeder::OPERATOR_EMAIL)->firstOrFail();
        $approver = User::query()->where('email', InventoryFeatureTestUsersSeeder::APPROVER_EMAIL)->firstOrFail();
        $this->assertOpeningModeReadyForFixture();

        DB::transaction(function () use ($operator, $approver): void {
            $location = InventoryLocation::query()->firstOrCreate([
                'name' => 'TEST ONLY - Gudang Inventori Demo',
            ], [
                'location_type' => 'warehouse',
                'is_restricted' => false,
            ]);
            $restrictedLocation = InventoryLocation::query()->firstOrCreate([
                'name' => 'TEST ONLY - Lokasi Terbatas Demo',
            ], [
                'location_type' => 'warehouse',
                'is_restricted' => true,
            ]);

            app(InventoryLotTrackingPolicyService::class)->updateMatrix($operator, [
                'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
                'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
                'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
                'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
                'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
            ], self::MARKER.' - keputusan kebijakan uji saja');

            $reagent = InventoryItem::query()->firstOrCreate(['item_code' => 'TEST-INV-REAGENT-001'], [
                'name' => 'TEST ONLY - Reagen Demo',
                'item_type' => 'REAGENT',
                'uom' => 'g',
                'min_stock' => 5,
                'is_active' => true,
            ]);
            $consumable = InventoryItem::query()->firstOrCreate(['item_code' => 'TEST-INV-CONSUMABLE-001'], [
                'name' => 'TEST ONLY - Bahan Habis Pakai Demo',
                'item_type' => 'CONSUMABLE',
                'uom' => 'pcs',
                'min_stock' => 10,
                'is_active' => true,
            ]);
            $other = InventoryItem::query()->firstOrCreate(['item_code' => 'TEST-INV-OTHER-001'], [
                'name' => 'TEST ONLY - Bahan Disposal Demo',
                'item_type' => 'OTHER',
                'uom' => 'pcs',
                'min_stock' => 2,
                'is_active' => true,
            ]);

            $lotA = $this->lot($reagent, 'TEST-LOT-2026-001', 30);
            $lotB = $this->lot($reagent, 'TEST-LOT-2026-002', 60);
            $this->receipt($operator, $reagent, $lotA, $location, 24.5, 'tracked_new', 1);
            $this->receipt($operator, $reagent, $lotB, $location, 18, 'tracked_new', 2);
            $this->receipt($operator, $reagent, $lotB, $restrictedLocation, 6, 'tracked_existing', 3);
            $this->receipt($operator, $consumable, null, $location, 40, 'untracked', 4);
            $this->receipt($operator, $other, null, $restrictedLocation, 12, 'untracked', 5);

            $access = app(InventoryLocationAccessService::class);
            $access->changeGrant($operator, $operator->id, $restrictedLocation->id, true, true, self::MARKER, ['surface' => 'test-fixture']);
            $access->changeGrant($operator, $approver->id, $restrictedLocation->id, true, true, self::MARKER, ['surface' => 'test-fixture']);

            $investigator = Investigator::query()->firstOrCreate(['email' => 'inventory.investigator@example.test'], [
                'name' => 'TEST ONLY - Penyidik Demo',
                'rank' => 'AKP',
                'nrp' => 'TEST-INV-001',
                'jurisdiction' => 'POLRES TEST ONLY',
                'phone' => '0000000000',
                'address' => self::MARKER,
                'is_polri' => true,
            ]);
            $request = TestRequest::query()->firstOrCreate(['case_number' => 'TEST-INV-DISPOSAL-001'], [
                'investigator_id' => $investigator->id,
                'user_id' => $operator->id,
                'suspect_name' => 'TEST ONLY - Subjek Demo',
                'case_description' => self::MARKER.' - sampel pemusnahan',
                'status' => 'completed',
                'letter_date' => now()->subDays(120)->toDateString(),
                'submitted_at' => now()->subDays(120),
                'completed_at' => now()->subDays(100),
            ]);
            foreach ([1, 2] as $number) {
                $sample = Sample::query()->firstOrCreate(['sample_code' => sprintf('TEST-DISPOSAL-%03d', $number)], [
                    'test_request_id' => $request->id,
                    'short_description' => 'TEST ONLY - Sampel '.$number,
                    'sample_description' => self::MARKER,
                    'sample_form' => 'powder',
                    'sample_category' => 'other',
                    'sample_status' => 'tested',
                    'disposal_status' => SampleDisposalStatus::PENDING->value,
                    'disposal_id' => null,
                    'disposed_at' => null,
                ]);
                SampleTestProcess::query()->updateOrCreate([
                    'sample_id' => $sample->id,
                    'stage' => 'interpretation',
                ], [
                    'completed_at' => now()->subDays(100),
                    'metadata' => ['lhu_number' => sprintf('TEST-ONLY-LHU-%03d', $number)],
                ]);
            }
            SystemSetting::query()->updateOrCreate(['key' => 'inventory.disposal_retention_days'], ['value' => 90]);
        });

        $this->command?->info('Fixture dummy berhasil disiapkan di database uji: '.DB::connection()->getDatabaseName());
        $this->command?->info('Barang: TEST-INV-REAGENT-001, TEST-INV-CONSUMABLE-001, TEST-INV-OTHER-001. Lokasi, lot, saldo, dan sampel memakai penanda TEST ONLY.');
        $this->command?->info('Dua sampel TEST-DISPOSAL-001/002 memenuhi syarat retensi 90 hari untuk menguji daftar pemusnahan.');
        $this->command?->warn('Mode perubahan stok tidak diaktifkan dan persetujuan kesiapan tidak dibuat. Selesaikan pencatatan stok awal dan persyaratan kesiapan sebelum menguji pemeriksaan stok berkala.');
    }

    private function lot(InventoryItem $item, string $lotNumber, int $expiryDays): InventoryLot
    {
        return InventoryLot::query()->firstOrCreate([
            'item_id' => $item->id,
            'lot_no' => $lotNumber,
        ], [
            'expiry_date' => now()->addDays($expiryDays)->toDateString(),
            'received_date' => now()->subDays(10)->toDateString(),
            'status' => 'ACTIVE',
            'notes' => self::MARKER,
        ]);
    }

    private function receipt(User $actor, InventoryItem $item, ?InventoryLot $lot, InventoryLocation $location, float $quantity, string $lotMode, int $sequence): void
    {
        $reference = 990000 + $sequence;
        $notes = self::MARKER.' - RECEIPT '.$sequence;
        if (! InventoryMovement::query()->where('notes', $notes)->exists()) {
            InventoryMovement::query()->create([
                'movement_type' => 'RECEIPT',
                'reference_type' => 'MANUAL',
                'reference_id' => $reference,
                'item_id' => $item->id,
                'lot_id' => $lot?->id,
                'lot_mode' => $lotMode,
                'from_location_id' => null,
                'to_location_id' => $location->id,
                'qty' => $quantity,
                'uom' => $item->uom,
                'performed_by' => $actor->id,
                'performed_at' => now()->subMinutes(5 - $sequence),
                'notes' => $notes,
            ]);
        }

        InventoryBalance::query()->firstOrCreate([
            'item_id' => $item->id,
            'lot_id' => $lot?->id,
            'location_id' => $location->id,
        ], [
            'on_hand_qty' => $quantity,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);
    }

    private function assertOpeningModeReadyForFixture(): void
    {
        $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode');
        if ($mode !== InventoryMutationMode::DISABLED) {
            throw new RuntimeException('Seeder fixture memerlukan mode perubahan stok tidak aktif. Gunakan database uji yang bersih; seeder tidak mengubah atau menonaktifkan mode secara otomatis.');
        }
    }
}
