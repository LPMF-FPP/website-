<?php

namespace Tests\Feature\Delivery;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EditInvestigatorFromDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_show_links_to_investigator_only_edit_page(): void
    {
        $this->seed(SystemSettingSeeder::class);
        $this->seed(PermissionSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false]);
        settings_forget_cache();
        Queue::fake();

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
        Sample::factory()->create([
            'test_request_id' => $request->id,
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('delivery.show', $request));

        $response->assertOk();
        $response->assertSee(route('delivery.investigator.edit', $request), false);
    }

    public function test_delivery_investigator_edit_updates_only_investigator_fields(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        $investigator = Investigator::factory()->create([
            'name' => 'AKP Lama',
            'nrp' => '87010123',
            'rank' => 'AKP',
            'jurisdiction' => 'POLRES LAMA',
            'phone' => '081200000001',
        ]);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
            'case_number' => 'SURAT-LAMA',
            'suspect_name' => 'Tersangka Lama',
        ]);

        $response = $this->actingAs($user)->patch(route('delivery.investigator.update', $request), [
            'investigator_name' => 'AKP Baru',
            'investigator_nrp' => '87010999',
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Polres Baru',
            'investigator_phone' => '081299999999',
            'investigator_email' => 'penyidik.baru@example.test',
            'investigator_address' => 'Alamat baru',
            'case_number' => 'SURAT-TIDAK-BOLEH-BERUBAH',
            'suspect_name' => 'Tersangka Tidak Boleh Berubah',
        ]);

        $response->assertRedirect(route('delivery.show', $request));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('investigators', [
            'id' => $investigator->id,
            'name' => 'AKP Baru',
            'nrp' => '87010999',
            'rank' => 'IPTU',
            'jurisdiction' => 'POLRES BARU',
            'phone' => '081299999999',
            'email' => 'penyidik.baru@example.test',
            'address' => 'Alamat baru',
        ]);

        $this->assertDatabaseHas('test_requests', [
            'id' => $request->id,
            'case_number' => 'SURAT-LAMA',
            'suspect_name' => 'Tersangka Lama',
        ]);
    }

    public function test_delivery_investigator_edit_requires_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'investigator']);
        $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);

        $this->actingAs($user)
            ->get(route('delivery.investigator.edit', $request))
            ->assertRedirect();
    }

    public function test_delivery_investigator_update_requires_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'investigator']);
        $investigator = Investigator::factory()->create(['name' => 'Nama Lama']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $this->actingAs($user)
            ->patch(route('delivery.investigator.update', $request), [
                'investigator_name' => 'Nama Baru',
                'investigator_nrp' => '87010998',
                'investigator_rank' => 'IPTU',
                'investigator_jurisdiction' => 'Polres Baru',
                'investigator_phone' => '081299999998',
                'investigator_email' => 'unauthorized@example.test',
                'investigator_address' => 'Alamat baru',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('investigators', [
            'id' => $investigator->id,
            'name' => 'Nama Lama',
        ]);
    }

    public function test_delivery_investigator_edit_requires_ready_for_delivery_status(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        $request = TestRequest::factory()->create(['status' => 'completed']);

        $this->actingAs($user)
            ->get(route('delivery.investigator.edit', $request))
            ->assertForbidden();
    }

    public function test_delivery_investigator_update_requires_ready_for_delivery_status(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        $investigator = Investigator::factory()->create(['name' => 'Nama Completed']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->patch(route('delivery.investigator.update', $request), [
                'investigator_name' => 'Nama Tidak Disimpan',
                'investigator_nrp' => '87010997',
                'investigator_rank' => 'IPTU',
                'investigator_jurisdiction' => 'Polres Baru',
                'investigator_phone' => '081299999997',
                'investigator_email' => 'completed@example.test',
                'investigator_address' => 'Alamat baru',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('investigators', [
            'id' => $investigator->id,
            'name' => 'Nama Completed',
        ]);
    }

    public function test_delivery_investigator_update_rejects_duplicate_email(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        Investigator::factory()->create(['email' => 'existing@example.test']);
        $investigator = Investigator::factory()->create(['email' => 'current@example.test']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $response = $this->actingAs($user)->patch(route('delivery.investigator.update', $request), [
            'investigator_name' => 'Nama Baru',
            'investigator_nrp' => $investigator->nrp,
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Polres Baru',
            'investigator_phone' => '081299999996',
            'investigator_email' => 'existing@example.test',
            'investigator_address' => 'Alamat baru',
        ]);

        $response->assertSessionHasErrors('investigator_email');

        $this->assertDatabaseHas('investigators', [
            'id' => $investigator->id,
            'email' => 'current@example.test',
        ]);
    }

    public function test_delivery_investigator_edit_rejects_shared_investigator_updates(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('investigators.edit');
        $investigator = Investigator::factory()->create([
            'name' => 'AKP Bersama',
            'nrp' => '87010001',
        ]);
        $targetRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);
        TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($user)->patch(route('delivery.investigator.update', $targetRequest), [
            'investigator_name' => 'AKP Tidak Disimpan',
            'investigator_nrp' => '87010002',
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Polres Baru',
            'investigator_phone' => '081299999999',
            'investigator_email' => 'shared-update@example.test',
            'investigator_address' => 'Alamat baru',
        ]);

        $response->assertSessionHasErrors('investigator');

        $this->assertDatabaseHas('investigators', [
            'id' => $investigator->id,
            'name' => 'AKP Bersama',
            'nrp' => '87010001',
        ]);
    }
}
