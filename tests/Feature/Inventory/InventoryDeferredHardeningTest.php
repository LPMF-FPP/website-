<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccessEvent;
use App\Models\SampleDisposal;
use App\Models\User;
use App\Services\InventoryLocationAccessService;
use App\Services\SampleDisposalFileService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryDeferredHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'pgsql'
            || ! in_array(DB::connection()->getDatabaseName(), ['lpmf_story_1_2_verify', 'lpmf_release_20261013_verify'], true)) {
            throw new \RuntimeException('Maintenance verification requires the approved PostgreSQL fixture database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_dashboard_counts_visible_unassociated_items_but_not_hidden_or_archived_items(): void
    {
        $actor = $this->viewer();
        InventoryItem::factory()->create();
        InventoryItem::factory()->create(['is_active' => false]);
        $visible = InventoryItem::factory()->create();
        $hidden = InventoryItem::factory()->create();
        $public = InventoryLocation::factory()->create(['is_restricted' => false]);
        $restricted = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryBalance::create(['item_id' => $visible->id, 'location_id' => $public->id, 'on_hand_qty' => 2]);
        InventoryBalance::create(['item_id' => $hidden->id, 'location_id' => $restricted->id, 'on_hand_qty' => 3]);
        $this->actingAs($actor)->get(route('inventory.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total_items'] === 2);
        $this->getJson(route('inventory.ajax.overview'))->assertOk()->assertJsonPath('total', 2);
    }

    public function test_view_only_balance_read_is_scoped_and_cannot_forge_transaction_context(): void
    {
        $actor = $this->viewer();
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryBalance::create(['item_id' => $item->id, 'location_id' => $location->id, 'on_hand_qty' => 5]);
        $params = ['item_id' => $item->id, 'location_id' => $location->id];
        $this->actingAs($actor)->getJson(route('inventory.ajax.balance', $params))->assertOk()->assertJsonPath('on_hand_qty', 5);
        $this->getJson(route('inventory.ajax.balance', [...$params, 'location_id' => $hidden->id]))->assertForbidden();
        $this->getJson(route('inventory.ajax.balance', [...$params, 'context' => 'issue']))->assertForbidden();
        $this->getJson(route('inventory.ajax.balance', [...$params, 'context' => 'unknown']))->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('inventory.ajax.balance', $params))->assertForbidden();
    }

    public function test_inactive_user_rights_are_visible_and_revocable_but_new_rights_are_denied(): void
    {
        $manager = $this->viewer();
        $manager->grantPermission('inventori.location-access.manage');
        $target = User::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $service = app(InventoryLocationAccessService::class);
        $service->changeGrant($manager, $target->id, $location->id, true, false);
        $target->update(['is_active' => false]);
        $this->actingAs($manager)->get(route('inventory.locations.access', $location))->assertOk()->assertSee('Nonaktif — hanya pencabutan');
        $url = route('inventory.locations.access.update', $location);
        $this->putJson($url, ['user_id' => $target->id, 'can_view' => true, 'can_transact' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertSame(1, InventoryLocationAccessEvent::count());
        $this->put($url, ['user_id' => $target->id, 'can_view' => false, 'can_transact' => false, 'reason' => 'Nonaktif'])->assertRedirect();
        $this->assertSame(2, InventoryLocationAccessEvent::count());
        $this->assertFalse($service->canViewLocation($target, $location));
        $this->assertFalse($service->canTransactLocation($target, $location));
        $this->putJson($url, ['user_id' => $target->id, 'can_view' => true])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_failed_cleanup_is_durable_and_retries_without_deleting_referenced_evidence(): void
    {
        $path = 'disposal-documentation/staged.jpg';
        $disk = Storage::disk('local');
        $disk->put($path, 'staged');
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('delete')->with($path)->once()->andReturn(false);
        Storage::set('local', $proxy);
        $service = app(SampleDisposalFileService::class);
        $service->cleanup([['disk' => 'local', 'path' => $path]]);
        $this->assertDatabaseHas('sample_disposal_file_operations', ['path' => $path, 'state' => 'pending', 'attempts' => 1]);
        Storage::set('local', $disk);
        $result = $service->retry();
        $this->assertSame(['completed' => 1, 'failed' => 0], $result);
        $disk->assertMissing($path);
        $protected = 'disposal-documentation/committed.jpg';
        $disk->put($protected, 'evidence');
        SampleDisposal::factory()->create(['documentation_photos' => [['disk' => 'local', 'path' => $protected]]]);
        $service->cleanup([['disk' => 'local', 'path' => $protected]]);
        $disk->assertExists($protected);
        $this->assertDatabaseHas('sample_disposal_file_operations', ['path' => $protected, 'state' => 'protected']);
    }

    public function test_legacy_privatization_is_dry_run_by_default_preserves_evidence_and_replays_safely(): void
    {
        $path = 'disposal-documentation/legacy.jpg';
        Storage::disk('public')->put($path, 'original-evidence');
        $record = SampleDisposal::factory()->create(['documentation_photos' => [['path' => $path, 'original_name' => 'Proof.jpg']]]);
        $before = $record->fresh()->getAttributes();
        $this->artisan('sample-disposal:privatize-files')->assertSuccessful();
        $this->assertSame(0, DB::table('sample_disposal_file_operations')->count());
        Storage::disk('public')->assertExists($path);
        $this->artisan('sample-disposal:privatize-files', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing($path);
        $destination = app(SampleDisposalFileService::class)->privateDestination($path);
        $this->assertSame('original-evidence', Storage::disk('local')->get($destination));
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertTrue($record->fresh()->documentation_photos_for_display[0]['exists']);
        $this->artisan('sample-disposal:privatize-files', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('sample_disposal_file_operations')->count());
    }

    public function test_private_mapping_commits_before_public_delete_and_failed_deletion_can_resume(): void
    {
        $path = 'disposal-documentation/retry.png';
        $disk = Storage::disk('public');
        $disk->put($path, 'original');
        SampleDisposal::factory()->create(['documentation_photos' => [['disk' => 'public', 'path' => $path]]]);
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('delete')->with($path)->once()->andThrow(new \RuntimeException('test deletion failure'));
        Storage::set('public', $proxy);
        $service = app(SampleDisposalFileService::class);
        $this->assertFalse($service->privatize($path));
        $this->assertDatabaseHas('sample_disposal_file_operations', ['path' => $path, 'state' => 'copied']);
        $destination = $service->privateDestination($path);
        Storage::disk('local')->assertExists($destination);
        $disk->assertExists($path);
        Storage::set('public', $disk);
        $this->artisan('sample-disposal:reconcile-files', ['--apply' => true])->assertSuccessful();
        $disk->assertMissing($path);
        Storage::disk('local')->assertExists($destination);
    }

    public function test_invalid_copy_or_missing_source_never_deletes_public_evidence(): void
    {
        $service = app(SampleDisposalFileService::class);
        $path = 'disposal-documentation/collision.jpg';
        Storage::disk('public')->put($path, 'original');
        $key = hash('sha256', 'privatize|public|'.$path);
        Storage::disk('local')->put('disposal-documentation/legacy-'.$key.'.jpg', 'different');
        $this->assertFalse($service->privatize($path));
        $this->assertNull($service->privateDestination($path));
        Storage::disk('public')->assertExists($path);
        $this->assertFalse($service->privatize('disposal-documentation/missing.jpg'));
        $this->expectException(\RuntimeException::class);
        $service->privatize('disposal-documentation/../elsewhere.jpg');
    }

    public function test_nested_relocation_is_rejected_and_completed_replay_detects_copy_corruption(): void
    {
        $service = app(SampleDisposalFileService::class);
        $path = 'disposal-documentation/corruption.jpg';
        Storage::disk('public')->put($path, 'original');
        DB::beginTransaction();
        try {
            $service->privatize($path);
            $this->fail('Relocation cannot delete files before an outer transaction commits.');
        } catch (\RuntimeException) {
            Storage::disk('public')->assertExists($path);
            $this->assertSame(0, DB::table('sample_disposal_file_operations')->count());
        } finally {
            DB::rollBack();
        }
        $this->assertTrue($service->privatize($path));
        $destination = $service->privateDestination($path);
        Storage::disk('local')->put($destination, 'corrupted');
        $this->assertFalse($service->privatize($path));
        $this->assertDatabaseHas('sample_disposal_file_operations', ['path' => $path, 'state' => 'copied', 'last_error' => 'private_copy_invalid']);
    }

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');

        return $user;
    }

    public function test_operational_preflight_reports_owner_blockers_without_creating_grants_or_signoffs(): void
    {
        $before = [DB::table('user_permissions')->count(), DB::table('inventory_readiness_signoffs')->count(), DB::table('inventory_item_identity_events')->count()];
        $this->artisan('inventory:operational-preflight')->expectsOutputToContain('Read-only:')->assertFailed();
        $this->assertSame($before, [DB::table('user_permissions')->count(), DB::table('inventory_readiness_signoffs')->count(), DB::table('inventory_item_identity_events')->count()]);
    }
}
