<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\SampleDisposalMethod;
use App\Enums\SampleDisposalStatus;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SampleDisposalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->grantPermission('inventori.view');
        $this->user->grantPermission('inventori.edit');
    }

    // ============================================
    // Model & Relationship Tests
    // ============================================

    public function test_sample_disposal_can_be_created(): void
    {
        $disposal = SampleDisposal::create([
            'batch_number' => 'DSP-2026-0001',
            'executed_at' => now(),
            'method' => SampleDisposalMethod::BAKAR,
            'witness_name' => 'Budi Santoso',
            'witness_role' => 'Kepala Lab',
            'notes' => 'Pemusnahan berjalan lancar',
            'executed_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('sample_disposals', [
            'batch_number' => 'DSP-2026-0001',
            'method' => 'bakar',
            'witness_name' => 'Budi Santoso',
        ]);
    }

    public function test_sample_disposal_has_samples_relationship(): void
    {
        $disposal = SampleDisposal::factory()->create();
        $sample = Sample::factory()->create([
            'disposal_id' => $disposal->id,
            'disposal_status' => SampleDisposalStatus::DISPOSED,
        ]);

        $this->assertTrue($disposal->samples->contains($sample));
        $this->assertCount(1, $disposal->samples);
    }

    public function test_sample_disposal_has_executed_by_relationship(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'executed_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $disposal->executedBy->id);
    }

    public function test_sample_disposal_has_created_by_relationship(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $disposal->createdBy->id);
    }

    public function test_sample_disposal_generates_unique_batch_number(): void
    {
        // First batch number should be DSP-YYYY-0001
        $batchNumber1 = SampleDisposal::generateBatchNumber();
        $this->assertStringStartsWith('DSP-', $batchNumber1);
        $this->assertMatchesRegularExpression('/^DSP-\d{4}-\d{4}$/', $batchNumber1);

        // Create a disposal with this batch number
        SampleDisposal::factory()->create([
            'batch_number' => $batchNumber1,
        ]);

        // Next batch number should be incremented
        $batchNumber2 = SampleDisposal::generateBatchNumber();
        $this->assertNotEquals($batchNumber1, $batchNumber2);
    }

    // ============================================
    // Sample Disposal Scopes Tests
    // ============================================

    public function test_sample_has_disposal_relationship(): void
    {
        $disposal = SampleDisposal::factory()->create();
        $sample = Sample::factory()->create([
            'disposal_id' => $disposal->id,
            'disposal_status' => SampleDisposalStatus::DISPOSED,
        ]);

        $this->assertEquals($disposal->id, $sample->disposal->id);
    }

    public function test_sample_scope_eligible_for_disposal(): void
    {
        $eligibleSample = $this->createEligibleSampleForDisposal();
        $nonEligibleSample = $this->createEligibleSampleForDisposal(daysAgo: 30);

        $eligibleSamples = Sample::eligibleForDisposal()->get();

        $this->assertTrue($eligibleSamples->contains($eligibleSample));
        $this->assertFalse($eligibleSamples->contains($nonEligibleSample));
    }

    public function test_sample_scope_disposed(): void
    {
        $disposal = SampleDisposal::factory()->create();

        $disposedSample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::DISPOSED,
            'disposal_id' => $disposal->id,
            'disposed_at' => now(),
        ]);

        $pendingSample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::PENDING,
        ]);

        $disposedSamples = Sample::disposed()->get();

        $this->assertTrue($disposedSamples->contains($disposedSample));
        $this->assertFalse($disposedSamples->contains($pendingSample));
    }

    public function test_sample_mark_as_eligible(): void
    {
        $sample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::PENDING,
        ]);

        $sample->markAsEligible();

        $this->assertEquals(SampleDisposalStatus::ELIGIBLE, $sample->fresh()->disposal_status);
    }

    public function test_sample_mark_as_disposed(): void
    {
        $disposal = SampleDisposal::factory()->create();
        $sample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        $sample->markAsDisposed($disposal);

        $sample->refresh();
        $this->assertEquals(SampleDisposalStatus::DISPOSED, $sample->disposal_status);
        $this->assertEquals($disposal->id, $sample->disposal_id);
        $this->assertNotNull($sample->disposed_at);
    }

    // ============================================
    // Controller Tests
    // ============================================

    public function test_disposal_index_shows_eligible_samples(): void
    {
        $sample = $this->createEligibleSampleForDisposal();

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.index'));

        $response->assertStatus(200);
        $response->assertSee($sample->sample_code);
    }

    public function test_disposal_create_shows_form(): void
    {
        $samples = collect([
            $this->createEligibleSampleForDisposal(),
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0002'),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.create', [
                'sample_ids' => $samples->pluck('id')->join(','),
            ]));

        $response->assertStatus(200);
        $response->assertSee('Metode Pemusnahan');
        $response->assertSee('Saksi (User)');
    }

    public function test_can_execute_batch_disposal(): void
    {
        $samples = collect([
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0010'),
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0011'),
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0012'),
        ]);
        $witness = User::factory()->create([
            'name' => 'Saksi Pengujian',
            'rank' => 'AKBP',
            'nrp' => '99887766',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => $samples->pluck('id')->toArray(),
                'method' => 'bakar',
                'witness_user_id' => $witness->id,
                'notes' => 'Pemusnahan berjalan lancar',
            ]);

        $response->assertRedirect();

        // Verify disposal record created
        $this->assertDatabaseHas('sample_disposals', [
            'method' => 'bakar',
            'witness_user_id' => $witness->id,
        ]);

        // Verify all samples marked as disposed
        foreach ($samples as $sample) {
            $this->assertEquals(
                SampleDisposalStatus::DISPOSED->value,
                $sample->fresh()->disposal_status->value
            );
        }
    }

    public function test_disposal_creates_audit_record(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0020');
        $witness = User::factory()->create([
            'name' => 'Test Witness',
            'rank' => 'KOMPOL',
            'nrp' => '11223344',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'hancur',
                'witness_user_id' => $witness->id,
            ]);

        $disposal = SampleDisposal::latest()->first();

        $this->assertNotNull($disposal);
        $this->assertEquals($this->user->id, $disposal->executed_by);
        $this->assertEquals($this->user->id, $disposal->created_by);
        $this->assertNotNull($disposal->executed_at);
    }

    public function test_disposal_store_requires_witness_input(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0030');

        $response = $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'bakar',
            ]);

        $response->assertSessionHasErrors(['witness_name', 'witness_role']);
    }

    public function test_can_execute_batch_disposal_with_manual_witness_fields(): void
    {
        $samples = collect([
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0040'),
            $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0041'),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => $samples->pluck('id')->toArray(),
                'method' => 'hancur',
                'witness_name' => 'Saksi Eksternal',
                'witness_role' => 'Perwakilan Penyidik',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('sample_disposals', [
            'method' => 'hancur',
            'witness_name' => 'Saksi Eksternal',
            'witness_role' => 'Perwakilan Penyidik',
            'witness_user_id' => null,
        ]);
    }

    public function test_disposal_store_rejects_inactive_witness_user(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0050');
        $inactiveWitness = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'bakar',
                'witness_user_id' => $inactiveWitness->id,
            ]);

        $response->assertSessionHasErrors('witness_user_id');
    }

    public function test_disposal_snapshot_remains_after_witness_profile_changes(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0060');
        $witness = User::factory()->create([
            'name' => 'Nama Lama',
            'rank' => 'AKBP',
            'nrp' => '88776655',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'bakar',
                'witness_user_id' => $witness->id,
            ]);

        $disposal = SampleDisposal::latest()->first();
        $this->assertNotNull($disposal);
        $this->assertStringContainsString('NAMA LAMA', strtoupper($disposal->witness_name));

        $witness->update([
            'name' => 'Nama Baru',
            'rank' => 'KOMBES POL.',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Nama Lama');
        $response->assertDontSee('Nama Baru');
    }

    public function test_can_download_berita_acara_pdf(): void
    {
        // Mock PdfRenderService
        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        $disposal = SampleDisposal::factory()->create();
        Sample::factory()->count(2)->create([
            'disposal_id' => $disposal->id,
            'disposal_status' => SampleDisposalStatus::DISPOSED,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.pdf', $disposal));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_disposal_show_displays_details(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'witness_name' => 'Test Witness',
            'witness_user_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Test Witness');
        $response->assertSee($disposal->batch_number);
    }

    public function test_sample_scope_eligible_for_disposal_includes_pending_production_samples(): void
    {
        $sample = $this->createEligibleSampleForDisposal(
            disposalStatus: SampleDisposalStatus::PENDING,
            lhuNumber: 'LHU-2025-0090'
        );

        $this->assertTrue(Sample::eligibleForDisposal()->get()->contains($sample));
    }

    public function test_disposal_index_requires_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('testing.index'))
            ->actingAs($user)
            ->get(route('inventory.disposal.index'));

        $response->assertRedirect(route('testing.index'));
        $response->assertSessionHas('error');
    }

    public function test_disposal_store_requires_inventory_edit_permission(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-0100');

        $response = $this->from(route('inventory.disposal.index'))
            ->actingAs($user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'bakar',
                'witness_name' => 'Saksi',
                'witness_role' => 'Petugas',
            ]);

        $response->assertRedirect(route('inventory.disposal.index'));
        $response->assertSessionHas('error');
    }

    private function createEligibleSampleForDisposal(
        int $daysAgo = 91,
        SampleDisposalStatus $disposalStatus = SampleDisposalStatus::PENDING,
        string $lhuNumber = 'LHU-2025-0001'
    ): Sample {
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'disposal_status' => $disposalStatus,
            'disposed_at' => null,
            'disposal_id' => null,
            'testing_completed_at' => now()->subDays($daysAgo),
        ]);

        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => 'interpretation',
            'completed_at' => now()->subDays($daysAgo),
            'metadata' => ['lhu_number' => $lhuNumber],
        ]);

        return $sample;
    }
}
