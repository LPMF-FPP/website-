<?php

namespace Tests\Browser\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMutationMode;
use App\Models\User;
use App\Services\InventoryLotTrackingPolicyService;
use Database\Seeders\PermissionSeeder;
use Laravel\Dusk\Browser;
use Tests\Concerns\RecreatesApprovedBrowserDatabase;
use Tests\DuskTestCase;
use Tests\Feature\Inventory\SeedsInventoryReservations;

class InventoryManagementTest extends DuskTestCase
{
    use RecreatesApprovedBrowserDatabase;
    use SeedsInventoryReservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recreateApprovedBrowserDatabase();
        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_view_inventory_dashboard(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->waitForText('Dashboard Inventori')
                ->assertSee('Dashboard Inventori')
                ->click('summary[aria-controls="inventory-data-links"]')
                ->assertSee('Master Item')
                ->click('summary[aria-controls="inventory-data-links"]')
                ->assertSee('Aksi cepat');
        });
    }

    public function test_user_can_view_inventory_items_list(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create(['name' => 'Test Reagent XYZ']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->waitForText('Master Item Inventori')
                ->assertSee('Master Item Inventori')
                ->assertSee($item->name)
                ->assertPresent('table');
        });
    }

    public function test_user_can_access_create_inventory_item_form(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items/create')
                ->waitForText('Tambah Item Baru')
                ->assertSee('Tambah Item Baru')
                ->assertPresent('select[name="item_type"]')
                ->assertPresent('input[name="name"]')
                ->assertPresent('input[name="brand"]')
                ->assertPresent('input[name="uom"]')
                ->assertPresent('input[name="min_stock"]')
                ->assertPresent('select[name="storage_condition"]');
        });
    }

    public function test_user_can_create_inventory_item(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items/create')
                ->waitForText('Tambah Item Baru')
                ->select('item_type', 'REAGENT')
                ->type('item_code', 'DUSK-REAGENT-01')
                ->type('name', 'New Test Reagent')
                ->type('brand', 'Test Brand')
                ->type('manufacturer', 'Test Manufacturer')
                ->type('uom', 'mL')
                ->press('Simpan')
                ->waitForText('Item berhasil ditambahkan')
                ->assertSee('Item berhasil ditambahkan');

            $this->assertDatabaseHas('inventory_items', [
                'item_code' => 'DUSK-REAGENT-01',
                'name' => 'New Test Reagent',
                'brand' => 'Test Brand',
            ]);
        });
    }

    public function test_user_can_update_inventory_item(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create(['name' => 'Original Name']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit("/referensi/inventori/items/{$item->id}/edit")
                ->waitForText('Ubah Item')
                ->assertSee('Ubah Item')
                ->assertInputValue('name', 'Original Name')
                ->clear('name')
                ->type('name', 'Updated Name')
                ->press('Simpan Perubahan')
                ->waitForText('Item berhasil diperbarui')
                ->assertSee('Item berhasil diperbarui');

            $item->refresh();
            $this->assertEquals('Updated Name', $item->name);
        });
    }

    public function test_receipt_lots_load_after_item_and_location_selection_and_retain_selection_when_toggling_modes(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-RECEIPT-ITEM-01',
            'item_type' => 'CONSUMABLE',
        ]);
        $user->grantPermission('inventori.location-access.manage');
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, [
            'CONSUMABLE' => InventoryLotTrackingPolicy::LOT_TRACKED,
        ], 'Kebijakan lot fixture browser');
        $firstLocation = InventoryLocation::factory()->create([
            'name' => 'Dusk Receipt Location A',
            'is_restricted' => false,
        ]);
        $secondLocation = InventoryLocation::factory()->create([
            'name' => 'Dusk Receipt Location B',
            'is_restricted' => false,
        ]);
        $firstLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-RECEIPT-LOT-A',
        ]);
        $secondLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-RECEIPT-LOT-B',
        ]);
        foreach ([[$firstLocation, $firstLot], [$secondLocation, $secondLot]] as [$location, $lot]) {
            $this->createInventoryBalanceWithReservedFixture([
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'on_hand_qty' => 2,
                'reserved_qty' => 0,
            ]);
        }
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->browse(function (Browser $browser) use ($user, $item, $firstLocation, $secondLocation, $firstLot, $secondLot): void {
            $browser->loginAs($user)
                ->visit(route('inventory.transaction.receipt'))
                ->waitFor('select[name="item_id"]')
                ->select('item_id', (string) $item->id)
                ->select('location_id', (string) $firstLocation->id)
                ->radio('lot_mode', 'existing')
                ->waitFor('select#lot-select option[value="'.$firstLot->id.'"]')
                ->assertSelectHasOption('lot_id', (string) $firstLot->id)
                ->assertSelectMissingOption('lot_id', (string) $secondLot->id)
                ->select('lot_id', (string) $firstLot->id)
                ->radio('lot_mode', 'new')
                ->radio('lot_mode', 'existing')
                ->assertSelected('lot_id', (string) $firstLot->id)
                ->select('location_id', (string) $secondLocation->id)
                ->waitFor('select#lot-select option[value="'.$secondLot->id.'"]')
                ->assertSelectHasOption('lot_id', (string) $secondLot->id)
                ->assertSelectMissingOption('lot_id', (string) $firstLot->id)
                ->select('lot_id', (string) $secondLot->id)
                ->type('qty', '1')
                ->press('Simpan penerimaan')
                ->waitForText('Penerimaan berhasil dicatat.')
                ->assertSee('Penerimaan berhasil dicatat.');
        });

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'lot_id' => $secondLot->id,
            'to_location_id' => $secondLocation->id,
            'qty' => 1,
        ]);
    }

    public function test_location_configuration_is_audited_and_restriction_is_locked_after_creation(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.location-access.manage');

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('inventory.locations.create'))
                ->waitFor('input[name="name"]')
                ->type('name', 'Dusk Location Configuration')
                ->select('location_type', 'cold_storage')
                ->check('input[type="checkbox"][name="is_restricted"]')
                ->press('Simpan')
                ->waitForText('Lokasi berhasil ditambahkan.')
                ->assertSee('Dusk Location Configuration');
        });

        $location = InventoryLocation::query()->where('name', 'Dusk Location Configuration')->firstOrFail();
        app(\App\Services\InventoryLocationAccessService::class)->changeGrant($user, $user->id, $location->id, true, false);
        $this->browse(function (Browser $browser) use ($user, $location): void {
            $browser->loginAs($user)
                ->visit(route('inventory.locations.edit', $location))
                ->waitForText('Riwayat konfigurasi lokasi')
                ->assertSee('Lokasi dibuat')
                ->assertSee('Cold Storage')
                ->assertChecked('input[type="checkbox"][name="is_restricted"]')
                ->assertDisabled('input[type="checkbox"][name="is_restricted"]')
                ->clear('name')
                ->type('name', 'Dusk Configuration Updated')
                ->select('location_type', 'lab')
                ->press('Simpan Perubahan')
                ->waitForText('Lokasi berhasil diperbarui.')
                ->assertSee('Lokasi berhasil diperbarui.')
                ->visit(route('inventory.locations.edit', $location))
                ->waitForText('Riwayat konfigurasi lokasi')
                ->assertSee('Konfigurasi diperbarui')
                ->assertSee('Dusk Configuration Updated')
                ->assertSee('Dusk Location Configuration');
        });

        $this->assertTrue($location->fresh()->is_restricted);
        $this->assertSame(2, $location->configurationEvents()->count());
    }

    public function test_counter_can_capture_an_explicit_zero_opening_baseline_without_stock_mutation(): void
    {
        $counter = User::factory()->create(['role' => 'penyelia']);
        $counter->grantPermission('inventori.view');
        $counter->grantPermission('inventori.stocktake.submit');
        $counter->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-BASELINE-ITEM-01',
            'item_type' => 'CONSUMABLE',
            'uom' => 'pcs',
        ]);
        InventoryLocation::factory()->create([
            'name' => 'Dusk Baseline Location',
            'location_type' => 'warehouse',
            'is_restricted' => false,
        ]);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($counter, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Keputusan owner INV-DUSK-02');

        $this->browse(function (Browser $browser) use ($counter, $item): void {
            $browser->loginAs($counter)
                ->visit(route('inventory.opening-baseline.index'))
                ->waitForText('Status perubahan stok: tidak aktif')
                ->press('Mulai pencatatan stok awal')
                ->waitForText('Status perubahan stok: pencatatan stok awal')
                ->press('Buat daftar barang yang harus dihitung')
                ->waitForText($item->name)
                ->assertSee('Dusk Baseline Location')
                ->type('physical_qty', '0')
                ->press('Simpan')
                ->waitForText('Jumlah fisik tersimpan.')
                ->assertSee('CATATAN SALDO')
                ->assertSee('Dihitung');
        });

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_counter_sees_unclassified_policy_blocker_in_opening_baseline(): void
    {
        $counter = User::factory()->create(['role' => 'penyelia']);
        $counter->grantPermission('inventori.view');
        $counter->grantPermission('inventori.stocktake.submit');
        $counter->grantPermission('inventori.location-access.manage');
        InventoryItem::factory()->create([
            'item_code' => 'DUSK-BASELINE-BLOCKED-01',
            'item_type' => 'CONSUMABLE',
            'uom' => 'pcs',
        ]);
        InventoryLocation::factory()->create([
            'name' => 'Dusk Baseline Blocker Location',
            'location_type' => 'warehouse',
            'is_restricted' => false,
        ]);

        $this->browse(function (Browser $browser) use ($counter): void {
            $browser->loginAs($counter)
                ->visit(route('inventory.opening-baseline.index'))
                ->waitForText('Status perubahan stok: tidak aktif')
                ->press('Mulai pencatatan stok awal')
                ->waitForText('Status perubahan stok: pencatatan stok awal')
                ->press('Buat daftar barang yang harus dihitung')
                ->waitForText('Penggunaan nomor lot belum ditentukan')
                ->assertSee('Daftar hitung belum lengkap')
                ->assertSee('Atur kebijakan nomor lot terlebih dahulu.');
        });

        $this->assertDatabaseCount('inventory_opening_baseline_lines', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_user_can_archive_an_item_through_the_inventory_list(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-ARCHIVE-01',
            'name' => 'Archive Dusk Item',
        ]);

        $this->browse(function (Browser $browser) use ($user, $item): void {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items?status=active')
                ->waitForText('Archive Dusk Item')
                ->click('table details summary')
                ->type('textarea[name="reason"]', 'Tidak digunakan lagi')
                ->press('Konfirmasi arsip')
                ->waitForText('Item berhasil diarsipkan.')
                ->assertSee('DUSK-ARCHIVE-01')
                ->assertSee('Diarsipkan');

            $this->assertFalse($item->fresh()->is_active);
        });
    }

    public function test_baseline_independent_browser_approval_records_immutable_zero_variance(): void
    {
        $counter = $this->inventoryAdmin();
        $counter->grantPermission('inventori.stocktake.submit');
        $counter->grantPermission('inventori.location-access.manage');
        $approver = User::factory()->create();
        $approver->grantPermission('inventori.view');
        $approver->grantPermission('inventori.stocktake.approve');
        InventoryItem::factory()->create(['item_type' => 'CONSUMABLE']);
        InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->classifyBrowserPolicies($counter);
        app(\App\Services\InventoryMutationModeService::class)->beginOpeningBaseline($counter);
        $baseline = app(\App\Services\InventoryOpeningBaselineService::class)->generate($counter);
        $this->browse(function (Browser $browser) use ($counter, $approver, $baseline): void {
            $browser->loginAs($counter)->visit(route('inventory.opening-baseline.show', $baseline))
                ->waitFor('[name="physical_qty"]')->type('physical_qty', '0')->press('Simpan')
                ->waitForText('Jumlah fisik tersimpan')->press('Kirim hasil hitung')
                ->waitForText('Menunggu pemeriksaan')->assertDontSee('Setujui dan catat selisih')
                ->loginAs($approver)->visit(route('inventory.opening-baseline.show', $baseline))
                ->waitForText('Pemeriksaan oleh petugas lain')->press('Setujui dan catat selisih')
                ->waitForText('Disetujui, hasil dikunci')->assertSee('Riwayat pemeriksaan');
        });
        $this->assertSame('approved', $baseline->fresh()->status);
        $this->assertDatabaseCount('inventory_opening_baseline_approvals', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_selected_cycle_browser_capture_submit_and_independent_variance_approval(): void
    {
        $counter = $this->inventoryAdmin();
        $counter->grantPermission('inventori.stocktake.submit');
        $counter->grantPermission('inventori.location-access.manage');
        $approver = User::factory()->create();
        $approver->grantPermission('inventori.view');
        $approver->grantPermission('inventori.stocktake.approve');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'name' => 'Cycle browser item']);
        InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->classifyBrowserPolicies($counter);
        InventoryMutationMode::findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $this->browse(function (Browser $browser) use ($counter, $approver, $item): void {
            $browser->loginAs($counter)->visit(route('inventory.cycle-counts.index'))->waitForText('Status perubahan stok: aktif')
                ->press('Mulai pemeriksaan stok')->waitForText($item->name)->press('Tambahkan ke pemeriksaan')
                ->waitFor('[name="physical_qty"]')->type('physical_qty', '2')->type('variance_reason', 'Fisik fixture dua unit')
                ->press('Simpan')->waitForText('Dihitung')->press('Kirim hasil hitung')
                ->waitForText('Menunggu persetujuan selisih')->assertDontSee('Setujui dan catat selisih');
            $count = \App\Models\InventoryCycleCount::firstOrFail();
            $browser->loginAs($approver)->visit(route('inventory.cycle-counts.show', $count))
                ->waitForText('Periksa selisih stok')->press('Setujui dan catat selisih')->waitForText('Selisih disetujui')
                ->assertSee('Riwayat pemeriksaan');
        });
        $this->assertSame('approved', \App\Models\InventoryCycleCount::firstOrFail()->status);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $item->id, 'on_hand_qty' => 2]);
    }

    private function classifyBrowserPolicies(User $actor): void
    {
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($actor, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Approved isolated browser fixture policy');
    }

    public function test_readiness_browser_requires_two_distinct_signers_and_keeps_activation_blocked(): void
    {
        $administrator = $this->inventoryAdmin();
        foreach (['inventori.stocktake.submit', 'inventori.location-access.manage', 'inventori.readiness.inventory-admin.accept', 'inventori.mutation.activate'] as $permission) {
            $administrator->grantPermission($permission);
        }
        $owner = User::factory()->create();
        $owner->grantPermission('inventori.view');
        $owner->grantPermission('inventori.readiness.system-owner.accept');
        app(\App\Services\InventoryMutationModeService::class)->beginOpeningBaseline($administrator);
        $category = array_key_first(\App\Models\InventoryReadinessEvidence::CATEGORIES);
        $selector = 'form:has(input[name="category"][value="'.$category.'"])';
        $this->browse(function (Browser $browser) use ($administrator, $owner, $selector): void {
            $browser->loginAs($administrator)->visit(route('inventory.opening-baseline.readiness'))
                ->waitForText('Perubahan stok belum bisa diaktifkan')
                ->assertDisabled('form[action="'.route('inventory.opening-baseline.activate').'"] button');
            $browser->within($selector, function (Browser $form): void {
                $form->type('evidence_reference', 'BROWSER-READINESS-APPROVED')->press('Simpan dokumen dan persetujuan');
            });
            $browser->waitForText('Nomor dokumen dan persetujuan kesiapan tersimpan')->loginAs($owner)->visit(route('inventory.opening-baseline.readiness'))
                ->waitForText('BROWSER-READINESS-APPROVED');
            $browser->within($selector, function (Browser $form): void {
                $form->assertSelected('signoff_role', 'system_owner')->press('Simpan dokumen dan persetujuan');
            });
            $browser->waitForText('Nomor dokumen dan persetujuan kesiapan tersimpan')->loginAs($administrator)->visit(route('inventory.opening-baseline.readiness'))
                ->waitForText('Disetujui')->assertDisabled('form[action="'.route('inventory.opening-baseline.activate').'"] button');
        });
        $this->assertDatabaseCount('inventory_readiness_evidence', 1);
        $this->assertDatabaseCount('inventory_readiness_signoffs', 2);
        $this->assertSame(InventoryMutationMode::OPENING_BASELINE, InventoryMutationMode::findOrFail(1)->mode);
    }

    public function test_issue_requires_an_explicit_lot_and_shows_on_hand_and_available_stock(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-ISSUE-ITEM-01',
            'item_type' => 'REAGENT',
            'uom' => 'g',
        ]);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
        ], 'Kebijakan lot fixture issue browser');
        $location = InventoryLocation::factory()->create([
            'name' => 'Dusk Issue Location',
            'is_restricted' => false,
        ]);
        $lot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-ISSUE-LOT-01',
            'status' => 'ACTIVE',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);
        $laterLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-ISSUE-LOT-02',
            'status' => 'ACTIVE',
            'expiry_date' => now()->addDays(20)->toDateString(),
        ]);
        foreach ([[$lot, 5], [$laterLot, 9]] as [$stockLot, $onHand]) {
            $this->createInventoryBalanceWithReservedFixture([
                'item_id' => $item->id,
                'lot_id' => $stockLot->id,
                'location_id' => $location->id,
                'on_hand_qty' => $onHand,
                'reserved_qty' => 2,
            ]);
        }
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->browse(function (Browser $browser) use ($user, $item, $location, $lot, $laterLot): void {
            $browser->loginAs($user)
                ->visit(route('inventory.transaction.issue'))
                ->waitFor('select[name="item_id"]');

            $browser->script('(() => {
                const originalFetch = window.fetch.bind(window);
                let delayFirstBalance = true;
                window.fetch = (input, init) => {
                    const response = originalFetch(input, init);
                    if (!String(input).includes("/ajax/balance") || !delayFirstBalance) return response;
                    delayFirstBalance = false;
                    return response.then((result) => result.json()).then((data) => new Promise((resolve) => {
                        window.setTimeout(() => resolve(new Response(JSON.stringify(data), { headers: { "Content-Type": "application/json" } })), 3000);
                    }));
                };
            })();');

            $browser->select('item_id', (string) $item->id)
                ->select('location_id', (string) $location->id)
                ->radio('lot_mode', 'existing')
                ->waitFor('select#lot-select option[value="'.$lot->id.'"]')
                ->assertSelected('lot_id', '')
                ->select('lot_id', (string) $lot->id);

            $lotOrder = $browser->script('return Array.from(document.querySelectorAll("#lot-select option")).map((option) => option.value).filter(Boolean);');
            $this->assertSame((string) $lot->id, $lotOrder[0][0]);
            $this->assertSame((string) $laterLot->id, $lotOrder[0][1]);

            $browser->select('lot_id', (string) $laterLot->id)
                ->waitForText('9.000 g')
                ->pause(3500)
                ->assertSee('9.000 g')
                ->assertDontSee('5.000 g')
                ->assertSee('Tersedia:')
                ->assertSee('7.000 g')
                ->type('input[name="qty"]', '1')
                ->press('Simpan pengeluaran')
                ->waitForText('Pengeluaran berhasil dicatat.')
                ->assertSee('DUSK-ISSUE-LOT-02')
                ->assertSee('Lihat riwayat stok barang ini');

            $this->assertDatabaseHas('inventory_movements', [
                'movement_type' => 'ISSUE',
                'item_id' => $item->id,
                'lot_id' => $laterLot->id,
                'qty' => 1,
            ]);
        });
    }

    public function test_transfer_uses_one_fefo_advisory_lot_and_confirms_equal_location_effects(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-TRANSFER-ITEM-01',
            'item_type' => 'REAGENT',
            'uom' => 'g',
        ]);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
        ], 'Kebijakan lot fixture transfer browser');
        $source = InventoryLocation::factory()->create(['name' => 'Dusk Transfer Source', 'is_restricted' => false]);
        $destination = InventoryLocation::factory()->create(['name' => 'Dusk Transfer Destination', 'is_restricted' => false]);
        $fefoLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-TRANSFER-LOT-A',
            'status' => 'ACTIVE',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);
        $laterLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'DUSK-TRANSFER-LOT-B',
            'status' => 'ACTIVE',
            'expiry_date' => now()->addDays(20)->toDateString(),
        ]);
        foreach ([[$fefoLot, $source, 5, 1], [$laterLot, $source, 9, 1], [$laterLot, $destination, 2, 0]] as [$lot, $location, $onHand, $reserved]) {
            $this->createInventoryBalanceWithReservedFixture([
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'on_hand_qty' => $onHand,
                'reserved_qty' => $reserved,
            ]);
        }
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->browse(function (Browser $browser) use ($user, $item, $source, $destination, $fefoLot, $laterLot): void {
            $browser->loginAs($user)
                ->visit(route('inventory.transaction.transfer'))
                ->waitFor('select[name="item_id"]');

            $browser->script('(() => {
                const originalFetch = window.fetch.bind(window);
                let delayFirstBalance = true;
                window.fetch = (input, init) => {
                    const response = originalFetch(input, init);
                    if (!String(input).includes("/ajax/balance") || !delayFirstBalance) return response;
                    delayFirstBalance = false;
                    return response.then((result) => result.json()).then((data) => new Promise((resolve) => {
                        window.setTimeout(() => resolve(new Response(JSON.stringify(data), { headers: { "Content-Type": "application/json" } })), 2500);
                    }));
                };
            })();');

            $browser->select('item_id', (string) $item->id)
                ->select('from_location_id', (string) $source->id)
                ->select('to_location_id', (string) $destination->id)
                ->radio('lot_mode', 'existing')
                ->waitFor('select#lot-select option[value="'.$fefoLot->id.'"]')
                ->assertSelected('lot_id', '')
                ->select('lot_id', (string) $fefoLot->id);

            $lotOrder = $browser->script('return Array.from(document.querySelectorAll("#lot-select option")).map((option) => option.value).filter(Boolean);');
            $this->assertSame((string) $fefoLot->id, $lotOrder[0][0]);
            $this->assertSame((string) $laterLot->id, $lotOrder[0][1]);

            $browser->select('lot_id', (string) $laterLot->id)
                ->waitForText('9.000 g')
                ->pause(3000)
                ->assertSee('9.000 g')
                ->assertDontSee('5.000 g')
                ->assertSee('8.000 g')
                ->assertSee('2.000 g')
                ->type('input[name="qty"]', '1')
                ->assertSee('Setelah transfer: 8.000 g')
                ->assertSee('Setelah transfer: 3.000 g')
                ->press('Simpan transfer')
                ->waitForText('Transfer berhasil dicatat.')
                ->assertSee('DUSK-TRANSFER-LOT-B')
                ->assertSee('Dusk Transfer Source')
                ->assertSee('Dusk Transfer Destination')
                ->assertSee('Buka kartu stok transfer');

            $this->assertDatabaseHas('inventory_movements', [
                'movement_type' => 'TRANSFER',
                'item_id' => $item->id,
                'lot_id' => $laterLot->id,
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 1,
            ]);
        });
    }

    public function test_material_disposal_requires_review_and_confirms_reason_and_tuple(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.material-disposal.create');
        $user->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create([
            'item_code' => 'DUSK-DISPOSAL-ITEM-01',
            'item_type' => 'OTHER',
            'uom' => 'pcs',
        ]);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, [
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Kebijakan lot fixture disposal browser');
        $location = InventoryLocation::factory()->create(['name' => 'Dusk Disposal Location', 'is_restricted' => false]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->browse(function (Browser $browser) use ($user, $item, $location): void {
            $browser->loginAs($user)
                ->visit(route('inventory.transaction.dispose'))
                ->waitFor('select[name="item_id"]')
                ->assertSee('bukan pemusnahan sampel')
                ->select('item_id', (string) $item->id)
                ->select('location_id', (string) $location->id)
                ->radio('lot_mode', 'untracked')
                ->waitForText('5.000 pcs')
                ->assertSee('4.000 pcs')
                ->type('input[name="qty"]', '2')
                ->select('reason_code', 'DAMAGE')
                ->assertSee('DUSK-DISPOSAL-ITEM-01')
                ->assertSee('Rusak')
                ->check('input[name="review_confirmed"]')
                ->press('Konfirmasi disposal material')
                ->waitForText('Disposal material inventori berhasil dicatat.')
                ->assertSee('Untracked, tanpa lot')
                ->assertSee('Dusk Disposal Location')
                ->assertSee('Movement');

            $this->assertDatabaseHas('inventory_movements', [
                'movement_type' => 'DISPOSE',
                'item_id' => $item->id,
                'lot_id' => null,
                'from_location_id' => $location->id,
                'reason_code' => 'DAMAGE',
                'qty' => 2,
            ]);
        });
    }

    public function test_disposal_balance_requests_discard_stale_responses_and_clear_context_on_authorization_failure(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.material-disposal.create');
        $user->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create(['item_code' => 'DUSK-DISPOSAL-RACE', 'item_type' => 'OTHER', 'uom' => 'pcs']);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, ['OTHER' => InventoryLotTrackingPolicy::UNTRACKED], 'Browser race fixture policy');
        $first = InventoryLocation::factory()->create(['name' => 'Dusk Disposal First', 'is_restricted' => false]);
        $second = InventoryLocation::factory()->create(['name' => 'Dusk Disposal Second', 'is_restricted' => false]);
        foreach ([[$first, 5], [$second, 8]] as [$location, $quantity]) {
            $this->createInventoryBalanceWithReservedFixture([
                'item_id' => $item->id, 'lot_id' => null, 'location_id' => $location->id,
                'on_hand_qty' => $quantity, 'reserved_qty' => 1,
            ]);
        }
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $this->browse(function (Browser $browser) use ($user, $item, $first, $second): void {
            $browser->loginAs($user)->visit(route('inventory.transaction.dispose'))->waitFor('select[name="item_id"]');
            $browser->script('(() => { const original = window.fetch.bind(window); window.__originalFetch = original; let delayed = false; window.fetch = (input, init) => { const result = original(input, init); if (delayed || !String(input).includes("/ajax/balance")) return result; delayed = true; return result.then(response => response.json()).then(data => new Promise(resolve => setTimeout(() => resolve(new Response(JSON.stringify(data), { headers: { "Content-Type": "application/json" } })), 2000))); }; })();');
            $browser->select('item_id', (string) $item->id)->radio('lot_mode', 'untracked')
                ->select('location_id', (string) $first->id)->select('location_id', (string) $second->id)
                ->waitForText('8.000 pcs')->pause(2200)->assertSee('8.000 pcs')->assertDontSee('5.000 pcs');
            $browser->script('const original = window.__originalFetch; window.fetch = (input, init) => String(input).includes("/ajax/balance") ? Promise.resolve(new Response("{}", {status: 403})) : original(input, init);');
            $browser->select('location_id', (string) $first->id)->waitForText('Gagal dimuat')
                ->assertDisabled('#dispose-submit')->assertInputValue('#expected-on-hand', '');
            $browser->script('window.fetch = window.__originalFetch;');
            $browser->select('location_id', (string) $second->id)->waitForText('8.000 pcs')
                ->type('input[name="qty"]', '1')->select('reason_code', 'DAMAGE')
                ->check('input[name="review_confirmed"]')->assertEnabled('#dispose-submit');
        });
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_shared_primary_buttons_keep_documented_pairs_across_themes_and_states(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('inventory.dashboard'))
                ->waitForText('Dashboard Inventori');

            $browser->script('document.body.innerHTML = `<button id="contrast-probe-start" type="button">Mulai pemeriksaan</button><button id="primary-contrast-probe" class="btn btn-primary">Primary</button><button id="semantic-primary-contrast-probe" class="btn btn-sem-primary">Semantic primary</button>`;');
            $lightBase = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).color, getComputedStyle(document.querySelector("#primary-contrast-probe")).backgroundColor, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).color, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor];');
            $this->assertSame(['rgb(255, 255, 255)', 'rgb(4, 120, 87)', 'rgb(255, 255, 255)', 'rgb(4, 120, 87)'], $lightBase[0]);

            $browser->keys('#contrast-probe-start', '{tab}')
                ->assertFocused('#primary-contrast-probe');
            $lightFocus = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).outlineWidth, getComputedStyle(document.querySelector("#primary-contrast-probe")).outlineColor];');
            $this->assertSame('3px', $lightFocus[0][0]);
            $this->assertSame('rgb(4, 120, 87)', $lightFocus[0][1]);

            $browser->mouseover('#primary-contrast-probe');
            $browser->pause(250);
            $lightPrimaryHover = $browser->script('return getComputedStyle(document.querySelector("#primary-contrast-probe")).backgroundColor;');
            $this->assertSame('rgb(6, 95, 70)', $lightPrimaryHover[0]);
            $browser->mouseover('#semantic-primary-contrast-probe')
                ->pause(250);
            $lightSemanticHover = $browser->script('return getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor;');
            $this->assertSame('rgb(6, 95, 70)', $lightSemanticHover[0]);

            $browser->script('document.querySelector("#primary-contrast-probe").disabled = true; document.querySelector("#semantic-primary-contrast-probe").disabled = true;');
            $browser->pause(250);
            $lightDisabled = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).color, getComputedStyle(document.querySelector("#primary-contrast-probe")).backgroundColor, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).color, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor];');
            $this->assertSame(['rgb(255, 255, 255)', 'rgb(6, 95, 70)', 'rgb(255, 255, 255)', 'rgb(6, 95, 70)'], $lightDisabled[0]);

            $browser->mouseover('#contrast-probe-start');
            $browser->script('document.documentElement.classList.add("dark"); document.documentElement.dataset.theme = "dark"; document.querySelector("#primary-contrast-probe").disabled = false; document.querySelector("#semantic-primary-contrast-probe").disabled = false;');
            $browser->pause(250);
            $darkBase = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).color, getComputedStyle(document.querySelector("#primary-contrast-probe")).backgroundColor, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).color, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor];');
            $this->assertSame(['rgb(15, 23, 42)', 'rgb(16, 185, 129)', 'rgb(15, 23, 42)', 'rgb(16, 185, 129)'], $darkBase[0]);

            $browser->keys('#contrast-probe-start', '{tab}')->assertFocused('#primary-contrast-probe');
            $darkFocus = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).outlineWidth, getComputedStyle(document.querySelector("#primary-contrast-probe")).outlineColor];');
            $this->assertSame(['3px', 'rgb(110, 231, 183)'], $darkFocus[0]);

            $browser->mouseover('#semantic-primary-contrast-probe');
            $browser->pause(250);
            $darkHover = $browser->script('return getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor;');
            $this->assertSame('rgb(52, 211, 153)', $darkHover[0]);

            $browser->script('document.querySelector("#primary-contrast-probe").disabled = true; document.querySelector("#semantic-primary-contrast-probe").disabled = true;');
            $browser->pause(250);
            $darkDisabled = $browser->script('return [getComputedStyle(document.querySelector("#primary-contrast-probe")).color, getComputedStyle(document.querySelector("#primary-contrast-probe")).backgroundColor, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).color, getComputedStyle(document.querySelector("#semantic-primary-contrast-probe")).backgroundColor];');
            $this->assertSame(['rgb(15, 23, 42)', 'rgb(110, 231, 183)', 'rgb(15, 23, 42)', 'rgb(110, 231, 183)'], $darkDisabled[0]);
        });
    }

    public function test_add_lot_dialog_opens_by_keyboard_and_returns_focus_after_escape(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $item): void {
            $browser->loginAs($user)
                ->visit(route('inventory.items.lots', $item))
                ->waitFor('#add-lot-trigger');

            $browserState = $browser->script('const dialog = document.querySelector("#add-lot-modal"); return { alpine: Boolean(window.Alpine), component: Boolean(dialog?._x_dataStack), open: dialog?._x_dataStack?.[0]?.open, componentFactory: typeof window.addInventoryLotDialog };');
            $this->assertTrue($browserState[0]['alpine'], json_encode($browserState[0]));
            $this->assertTrue($browserState[0]['component'], json_encode($browserState[0]));

            $browser->keys('#add-lot-trigger', '{ENTER}');
            $browser->assertVisible('#add-lot-modal')
                ->assertAttribute('#add-lot-modal', 'aria-modal', 'true')
                ->assertFocused('#add-lot-modal button[aria-label="Tutup formulir lot"]');

            $browser->keys('#add-lot-modal button[aria-label="Tutup formulir lot"]', '{ESCAPE}');
            $browser->waitUntilMissing('#add-lot-modal:not([style*="display: none"])')
                ->assertFocused('#add-lot-trigger');
        });
    }

    public function test_overview_fetch_failure_announces_error_and_offers_retry_without_stale_rows(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('inventory.dashboard'))
                ->waitForText('Dashboard Inventori');

            $browser->script('(() => {
                const originalFetch = window.fetch.bind(window);
                window.__story41OriginalFetch = originalFetch;
                window.fetch = (input, init) => {
                    const url = String(input);

                    return url.includes("/ajax/overview")
                        ? new Promise((resolve) => window.setTimeout(() => resolve(new Response("{}", { status: 503 })), 250))
                        : originalFetch(input, init);
                };
            })();');

            $browser->type('input[placeholder="Cari item..."]', 'RETRY-STATUS')
                ->waitForText('Ringkasan inventori gagal dimuat.')
                ->assertSee('Coba lagi')
                ->assertDontSee('Tidak ada item yang sesuai dengan pencarian.');

            $browser->script('window.fetch = window.__story41OriginalFetch;');
            $browser->click('[x-data="inventoryOverview()"] button[\@click="fetchData()"]')
                ->waitForText('Tidak ada item yang sesuai dengan pencarian.')
                ->assertDontSee('Ringkasan inventori gagal dimuat.')
                ->assertSee('Tidak ada item yang sesuai dengan pencarian.');
        });
    }

    public function test_user_can_filter_inventory_items_by_type(): void
    {
        $user = $this->inventoryAdmin();
        $reagent = InventoryItem::factory()->create([
            'item_type' => 'REAGENT',
            'name' => 'Special Reagent ABC',
        ]);
        $consumable = InventoryItem::factory()->create([
            'item_type' => 'CONSUMABLE',
            'name' => 'Regular Consumable XYZ',
        ]);

        $this->browse(function (Browser $browser) use ($user, $reagent, $consumable) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->waitForText('Master Item Inventori')
                ->assertSee($reagent->name)
                ->assertSee($consumable->name)
                ->select('type', 'CONSUMABLE')
                ->press('Terapkan filter')
                ->waitForText($consumable->name)
                ->assertSee($consumable->name)
                ->assertDontSee($reagent->name);
        });
    }

    public function test_inventory_async_widgets_discard_stale_results_and_support_keyboard_recovery(): void
    {
        $user = $this->inventoryAdmin();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)->visit(route('inventory.dashboard'))->waitForText('Dashboard Inventori');
            $browser->waitUntil('Boolean(document.querySelector("[x-data=\"inventoryOverview()\"]")?._x_dataStack?.[0]?.fetchData)');
            $browser->script('window.__story41Errors = []; window.addEventListener("error", event => window.__story41Errors.push(event.message));');

            $browser->keys('button[aria-label^="Lihat lot kedaluwarsa"]', '{ENTER}')
                ->assertAttribute('#inventory-alert-expired', 'aria-pressed', 'true')
                ->waitFor('#inventory-panel-expired')
                ->assertVisible('#inventory-panel-expired');

            $browser->script('window.__story41Fetch = window.fetch; window.fetch = (input) => new Promise(resolve => {
                const q = new URL(String(input), location.origin).searchParams.get("q");
                const stale = q === "OLD";
                const item = { id: stale ? 9001 : 9002, name: stale ? "STALE-ROW" : "CURRENT-ROW", item_code: "VERIFY", total_stock: 1, status: "ok", uom: "g", balances: [], lots: [] };
                setTimeout(() => resolve(new Response(JSON.stringify({ data: [item], total: 1, current_page: 1 }), {status: 200})), stale ? 600 : 50);
            }); const state = document.querySelector("[x-data=\"inventoryOverview()\"]")._x_dataStack[0]; state.search = "OLD";');
            $browser->pause(550);
            $browser->type('input[placeholder="Cari item..."]', 'NEW')
                ->waitForText('CURRENT-ROW')->pause(700)->assertDontSee('STALE-ROW');

            $browser->script('window.fetch = (input) => new Promise(resolve => {
                const q = new URL(String(input), location.origin).searchParams.get("q");
                const stale = q === "OLD";
                const row = { id: stale ? 9101 : 9102, type: "item", label: stale ? "STALE-SEARCH" : "CURRENT-SEARCH", view_url: "/unused-verification-target" };
                setTimeout(() => resolve(new Response(JSON.stringify({results: [row]}), {status:200})), stale ? 700 : 50);
            });');
            $browser->type('#inventory-global-search', 'OLD')->pause(300)
                ->type('#inventory-global-search', 'NEW')->waitForText('CURRENT-SEARCH')
                ->pause(700)->assertDontSee('STALE-SEARCH');

            $browser->script('window.fetch = () => Promise.resolve(new Response("{}", {status:503}));');
            $browser->type('#inventory-global-search', 'FAIL')->waitForText('Pencarian gagal dimuat.')
                ->assertDontSee('CURRENT-SEARCH');
            $browser->script('window.fetch = () => Promise.resolve(new Response(JSON.stringify({results: []}), {status:200}));');
            $browser->click('#inventory-global-search-results button[\@click="search()"]')
                ->waitForText('Tidak ada item atau lot yang cocok.');

            $browser->script('window.fetch = () => Promise.resolve(new Response("{}", {status:503}));');
            $browser->keys('button[aria-haspopup="dialog"]', '{ENTER}')
                ->waitForText('Data pengeluaran gagal dimuat.')
                ->assertFocused('[x-ref="closeButton"]');
            $browser->script('window.fetch = () => Promise.resolve(new Response(JSON.stringify({data: []}), {status:200}));');
            $browser->click('button[\@click="fetchData(retryUrl)"]')
                ->waitForText('Belum ada pengeluaran stok untuk ditampilkan dalam periode ini.');
            $browser->keys('[x-ref="closeButton"]', '{ESCAPE}')
                ->assertFocused('button[aria-haspopup="dialog"]');
            $errors = $browser->script('return window.__story41Errors;');
            $this->assertSame([], $errors[0]);
            $browser->script('window.fetch = window.__story41Fetch;');
        });
    }

    public function test_stock_card_lot_lookup_discards_stale_options_and_recovers_from_failure(): void
    {
        $user = $this->inventoryAdmin();
        $first = InventoryItem::factory()->create();
        $second = InventoryItem::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $first, $second): void {
            $browser->loginAs($user)->visit(route('inventory.stock-card'))->waitFor('#item-select');
            $browser->script('window.__story41FirstItem = '.(int) $first->id.'; window.fetch = (input) => new Promise(resolve => {
                const old = Number(new URL(String(input), location.origin).searchParams.get("item_id")) === window.__story41FirstItem;
                setTimeout(() => resolve(new Response(JSON.stringify([{ id: old ? 9901 : 9902, lot_no: old ? "STALE-LOT" : "CURRENT-LOT" }]), {status:200})), old ? 700 : 50);
            });');
            $browser->select('item_id', (string) $first->id)->pause(100)
                ->select('item_id', (string) $second->id)->waitForText('1 lot tersedia.')
                ->pause(800)->assertSelectHasOption('lot_id', '9902')
                ->assertSelectMissingOption('lot_id', '9901');
            $browser->script('window.fetch = () => Promise.resolve(new Response("{}", {status:503}));');
            $browser->select('item_id', (string) $first->id)
                ->waitForText('Daftar lot gagal dimuat.')
                ->assertSelectMissingOption('lot_id', '9902');
            $browser->script('window.fetch = () => Promise.resolve(new Response("[]", {status:200}));');
            $browser->click('#stock-card-lot-retry')->waitForText('Tidak ada lot untuk item pada lokasi terpilih.')
                ->assertEnabled('#lot-select');
        });
    }

    public function test_inventory_dashboard_shows_quick_action_buttons(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.material-disposal.create');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->waitForText('Dashboard Inventori')
                ->assertSee('Aksi cepat')
                ->assertSee('Terima stok')
                ->assertSee('Pengeluaran Stok')
                ->assertSee('Transfer Stok')
                ->assertSee('Buang Stok')
                ->assertPresent('a[href="'.route('inventory.transaction.issue').'"]')
                ->assertPresent('a[href="'.route('inventory.transaction.receipt').'"]')
                ->assertPresent('a[href="'.route('inventory.transaction.transfer').'"]');

            foreach (['receipt' => '#receipt-submit', 'issue' => '#issue-submit', 'transfer' => '#transfer-submit', 'dispose' => '#dispose-submit'] as $action => $submit) {
                $browser->visit(route('inventory.dashboard'))->waitForText('Aksi cepat')
                    ->click('nav[aria-label="Aksi inventori"] a[href="'.route('inventory.transaction.'.$action).'"]')
                    ->waitFor($submit)->assertPresent($submit);
            }
        });
    }

    public function test_inventory_navigation_tracks_the_current_page_and_theme_on_desktop(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.location-access.manage');
        $user->grantPermission('inventori.stocktake.submit');
        $user->grantPermission('sample-disposal.view');

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('inventory.items.index'))
                ->waitForText('Master Item Inventori')
                ->assertAttribute('summary[data-current-group="true"]', 'aria-controls', 'inventory-data-links')
                ->click('summary[aria-controls="inventory-data-links"]')
                ->assertVisible('#inventory-data-links')
                ->assertAttribute('#inventory-data-links a[href="'.route('inventory.items.index').'"]', 'aria-current', 'page')
                ->keys('summary[aria-controls="inventory-data-links"]', '{ESCAPE}')
                ->assertFocused('summary[aria-controls="inventory-data-links"]');

            $this->assertTrue($browser->script('return !document.querySelector("#inventory-data-links").closest("details").open;')[0]);

            $browser
                ->click('summary[aria-controls="inventory-sample-links"]')
                ->assertVisible('#inventory-sample-links a[href="'.route('inventory.disposal.index').'"]')
                ->click('summary[aria-controls="inventory-data-links"]')
                ->assertVisible('#inventory-data-links');

            $lightState = $browser->script('const nav = document.querySelector("nav[aria-label=\\"Navigasi inventori\\"]"); const current = nav.querySelector("[aria-current=page]"); const items = [...nav.querySelectorAll(":scope > ul > li")]; return { width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth, rows: new Set(items.map((item) => Math.round(item.getBoundingClientRect().top))).size, height: nav.getBoundingClientRect().height, background: getComputedStyle(current).backgroundColor };');
            $this->assertFalse($lightState[0]['overflow']);
            $this->assertSame(1, $lightState[0]['rows']);
            $this->assertLessThanOrEqual(64, $lightState[0]['height']);
            $browser->script('document.querySelector("#item-search").click();');
            $this->assertTrue($browser->script('return [...document.querySelectorAll("nav[aria-label=\\"Navigasi inventori\\"] details")].every((menu) => !menu.open);')[0]);
            $browser->click('summary[aria-controls="inventory-data-links"]');
            $browser->script('window.__setTheme("dark");');
            $browser->pause(450);
            $darkState = $browser->script('const nav = document.querySelector("nav[aria-label=\\"Navigasi inventori\\"]"); const current = nav.querySelector("[aria-current=page]"); return { background: getComputedStyle(current).backgroundColor, color: getComputedStyle(current).color };');

            $this->assertNotSame($lightState[0]['background'], $darkState[0]['background']);
            $this->assertNotSame($darkState[0]['background'], $darkState[0]['color']);
        });
    }

    public function test_statistics_dashboard_announces_empty_chart_states_and_keeps_both_themes(): void
    {
        $user = User::factory()->create(['role' => 'analis']);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit(route('statistics.index'))
                ->waitForText('Statistik dan rekap data')
                ->waitFor('p[data-chart-empty="suspectGender"]:not(.hidden)')
                ->assertSee('Belum ada data jenis kelamin pada permintaan.')
                ->assertDontSee('Unduh PNG')
                ->assertAttribute('[role="group"][aria-label="Tampilan statistik"] button:first-child', 'aria-pressed', 'true');

            $browser->script('window.__setTheme("light");');
            $browser->pause(450);
            $light = $browser->script('const title = document.querySelector(".statistics-page-header h2"); const surface = document.querySelector(".statistics-tabs"); return { title: getComputedStyle(title).color, surface: getComputedStyle(surface).backgroundColor, overflow: document.documentElement.scrollWidth > innerWidth };');
            $this->assertFalse($light[0]['overflow']);

            $browser->script('window.__setTheme("dark");');
            $browser->pause(450);
            $dark = $browser->script('const title = document.querySelector(".statistics-page-header h2"); const surface = document.querySelector(".statistics-tabs"); return { title: getComputedStyle(title).color, surface: getComputedStyle(surface).backgroundColor };');
            $this->assertNotSame($light[0]['title'], $dark[0]['title']);
            $this->assertNotSame($light[0]['surface'], $dark[0]['surface']);
        });
    }

    private function inventoryAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.create');
        $user->grantPermission('inventori.edit');
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.issue.create');
        $user->grantPermission('inventori.transfer.create');

        return $user;
    }

    public function test_epic_four_alert_queue_and_report_filters_survive_navigation_and_keyboard_use(): void
    {
        $user = $this->inventoryAdmin();
        $item = InventoryItem::factory()->create(['item_code' => 'EPIC4-REPORT', 'min_stock' => 20]);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        \App\Models\InventoryBalance::create(['item_id' => $item->id, 'location_id' => $location->id, 'on_hand_qty' => 3.123]);
        \App\Models\InventoryMovement::factory()->create(['item_id' => $item->id, 'movement_type' => 'RECEIPT', 'to_location_id' => $location->id, 'qty' => '3.123', 'performed_at' => '2026-09-01 12:00:00']);

        $this->browse(function (Browser $browser) use ($user, $item): void {
            $browser->loginAs($user)->visit(route('inventory.dashboard', ['tab' => 'expired']))
                ->waitForText('Dashboard Inventori')->assertAttribute('#inventory-alert-expired', 'aria-pressed', 'true')
                ->keys('#inventory-alert-low-stock', '{ENTER}')->waitFor('#inventory-panel-low-stock');
            $url = $browser->driver->getCurrentURL();
            $this->assertStringContainsString('tab=low_stock', $url);
            $browser->click('#inventory-panel-low-stock a[href="'.route('inventory.alerts.index', ['category' => 'low_stock']).'"]')
                ->waitForText('Daftar peringatan')->assertSee('EPIC4-REPORT');
            $browser->visit(route('inventory.stock-card', ['item_id' => $item->id, 'date_from' => '2026-09-05', 'date_to' => '2026-09-06']))
                ->waitForText('Ringkasan stok 2026-09-05 sampai 2026-09-06')->assertSee('3,123')->assertSee('Jumlah stok pada akhir periode')
                ->assertInputValue('date_from', '2026-09-05')->assertInputValue('date_to', '2026-09-06');
            $browser->script('window.__setTheme("dark");');
            $browser->assertSee('Saldo awal')->assertSee('Saldo akhir');
            $pdfUrl = $browser->attribute('a[href*="/kartu-stok/cetak"]', 'href');
            $this->assertStringContainsString('date_from=2026-09-05', $pdfUrl);
        });
    }

    public function test_epic_four_transaction_review_updates_exact_tuple_and_disables_unready_commit(): void
    {
        $user = $this->inventoryAdmin();
        $user->grantPermission('inventori.location-access.manage');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'item_code' => 'EPIC4-REVIEW', 'uom' => 'g']);
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, ['CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED], 'Browser review policy');
        $source = InventoryLocation::factory()->create(['is_restricted' => false, 'name' => 'Review source']);
        $destination = InventoryLocation::factory()->create(['is_restricted' => false, 'name' => 'Review destination']);
        \App\Models\InventoryBalance::create(['item_id' => $item->id, 'location_id' => $source->id, 'on_hand_qty' => 10]);
        InventoryMutationMode::query()->updateOrCreate(['id' => 1], ['mode' => InventoryMutationMode::ACTIVE]);

        $this->browse(function (Browser $browser) use ($user, $item, $source, $destination): void {
            foreach (['receipt', 'issue', 'transfer'] as $operation) {
                $browser->loginAs($user)->visit(route('inventory.transaction.'.$operation))
                    ->waitForText('3. Tinjau transaksi')->assertDisabled('#'.$operation.'-submit')
                    ->select('item_id', (string) $item->id);
                $layout = $browser->script('const form = document.getElementById("'.$operation.'-form"); const grid = form.querySelector(".grid.items-start"); const fields = grid.firstElementChild.getBoundingClientRect(); const review = grid.querySelector("aside").getBoundingClientRect(); return { fieldsLeft: fields.left, fieldsTop: fields.top, reviewLeft: review.left, reviewTop: review.top };');
                $this->assertGreaterThan($layout[0]['fieldsLeft'], $layout[0]['reviewLeft']);
                $this->assertEquals($layout[0]['fieldsTop'], $layout[0]['reviewTop']);
                if ($operation === 'transfer') {
                    $browser->select('from_location_id', (string) $source->id)->select('to_location_id', (string) $destination->id);
                } else {
                    $browser->select('location_id', (string) $source->id);
                }
                $browser->radio('lot_mode', 'untracked')->type('qty', '2.125')
                    ->waitUntil('!document.querySelector("#'.$operation.'-submit").disabled')
                    ->assertSee('EPIC4-REVIEW')->assertSee('2,125 g')->assertSee('Review source');
                if ($operation === 'transfer') {
                    $browser->assertSee('Review destination')->assertSee('Total inventori tetap.');
                }
                $browser->type('qty', '11.000');
                if ($operation !== 'receipt') {
                    $browser->assertDisabled('#'.$operation.'-submit');
                }
                if ($operation === 'issue') {
                    $browser->script('HTMLFormElement.prototype.submit.call(document.getElementById("issue-form"));');
                    $browser->waitFor('[role="alert"][tabindex="-1"]')
                        ->assertFocused('[role="alert"][tabindex="-1"]')
                        ->assertInputValue('qty', '11.000')
                        ->click('[role="alert"][tabindex="-1"] a')
                        ->assertFocused('#issue-form')
                        ->type('qty', '-1');
                    $browser->script('HTMLFormElement.prototype.submit.call(document.getElementById("issue-form"));');
                    $browser->waitFor('[role="alert"][tabindex="-1"]')
                        ->click('[role="alert"][tabindex="-1"] a')
                        ->assertFocused('input[name="qty"]');
                }
                $browser->type('qty', '2.125');
                $browser->script('window.__setTheme("dark");');
                $browser->assertSee('3. Tinjau transaksi');
                $browser->select('item_id', '')->assertDisabled('#'.$operation.'-submit');
            }
        });
    }
}
