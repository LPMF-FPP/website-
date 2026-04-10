<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\SampleDisposalMethod;
use App\Enums\SampleDisposalStatus;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
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
            'batch_number' => 'DSP-TEST-'.uniqid(),
            'executed_at' => now(),
            'method' => SampleDisposalMethod::BAKAR,
            'witness_name' => 'Budi Santoso',
            'witness_role' => 'Kepala Lab',
            'notes' => 'Pemusnahan berjalan lancar',
            'executed_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('sample_disposals', [
            'batch_number' => $disposal->batch_number,
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

    public function test_disposal_index_shows_test_request_suspect_name(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-SUSPECT-0001');

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.index'));

        $response->assertStatus(200);

        $eligibleSamples = $response->viewData('eligibleSamples');
        $matchedSample = $eligibleSamples->getCollection()->firstWhere('id', $sample->id);

        $this->assertNotNull($matchedSample);
        $this->assertSame($sample->fresh()->testRequest->suspect_name, $matchedSample->testRequest?->suspect_name);
    }

    public function test_disposal_create_shows_form(): void
    {
        $samples = collect(range(1, 22))->map(function (int $index) {
            return $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT));
        });

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.create', [
                'sample_ids' => $samples->pluck('id')->join(','),
            ]));

        $response->assertStatus(200);
        $response->assertSee('Metode Pemusnahan');
        $response->assertSee('Daftar Saksi');
        $response->assertSee((string) $samples->count());
    }

    public function test_disposal_create_can_load_all_eligible_samples(): void
    {
        collect(range(1, 25))->map(function (int $index) {
            return $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-ALL-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT));
        });

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.create', ['all' => 1]));

        $response->assertStatus(200);
        $response->assertSee('Tersangka LHU-ALL-0001');
        $response->assertSee('Tersangka LHU-ALL-0025');
    }

    public function test_disposal_create_rejects_partial_batch_when_any_sample_is_no_longer_eligible(): void
    {
        $eligibleSample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-2025-1111');
        $ineligibleSample = $this->createEligibleSampleForDisposal(daysAgo: 5, lhuNumber: 'LHU-2025-1112');

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.create', [
                'sample_ids' => [$eligibleSample->id, $ineligibleSample->id],
            ]));

        $response->assertRedirect(route('inventory.disposal.index', ['tab' => 'eligible']));
        $response->assertSessionHas('error');
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
                'executor_name' => 'Pelaksana Manual',
                'executor_role' => 'Ketua Tim Pemusnahan',
                'executor_identity' => 'NRP: 99880011',
                'witnesses' => [
                    ['user_id' => $witness->id, 'name' => '', 'role' => '', 'identity' => 'NRP: 99887766'],
                ],
                'approver_name' => 'Kombes Manual',
                'approver_role' => 'KBP',
                'approver_identity' => 'NRP: 77889900',
                'notes' => 'Pemusnahan berjalan lancar',
            ]);

        $response->assertRedirect();

        // Verify disposal record created
        $this->assertDatabaseHas('sample_disposals', [
            'method' => 'bakar',
            'witness_user_id' => $witness->id,
            'executed_by_name' => 'Pelaksana Manual',
            'executed_by_role' => 'Ketua Tim Pemusnahan',
            'executed_by_identity' => 'NRP: 99880011',
            'approver_name' => 'Kombes Manual',
            'approver_role' => 'KBP',
            'approver_identity' => 'NRP: 77889900',
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
                'witnesses' => [
                    ['user_id' => $witness->id, 'name' => '', 'role' => ''],
                ],
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

        $response->assertSessionHasErrors(['witnesses']);
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
                'witnesses' => [
                    ['user_id' => '', 'name' => 'Saksi Eksternal', 'role' => 'Perwakilan Penyidik', 'identity' => 'NRP: 12345678'],
                    ['user_id' => '', 'name' => 'Saksi Kedua', 'role' => 'Pengawas', 'identity' => 'NRP: 87654321'],
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('sample_disposals', [
            'method' => 'hancur',
            'witness_name' => 'Saksi Eksternal',
            'witness_role' => 'Perwakilan Penyidik',
            'witness_user_id' => null,
        ]);

        $disposal = SampleDisposal::latest()->first();
        $this->assertCount(2, $disposal->witness_entries);
        $this->assertSame('NRP: 12345678', $disposal->witness_entries[0]['identity']);
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
                'witnesses' => [
                    ['user_id' => $inactiveWitness->id, 'name' => '', 'role' => ''],
                ],
            ]);

        $response->assertSessionHasErrors('witnesses.0.user_id');
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
                'witnesses' => [
                    ['user_id' => $witness->id, 'name' => '', 'role' => ''],
                ],
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

    public function test_pdf_view_renders_multiple_witnesses_and_legacy_fallback(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'witness_entries' => [
                ['name' => 'Saksi Satu', 'role' => 'Penyidik', 'identity' => 'NRP: 123'],
                ['name' => 'Saksi Dua', 'role' => 'Kepala Lab', 'identity' => 'NIP: 456'],
            ],
            'witness_name' => 'Saksi Satu',
            'witness_role' => 'Penyidik',
        ]);

        $pdfHtml = view('pdf.berita-acara-pemusnahan', [
            'disposal' => $disposal->load(['samples.testRequest.investigator', 'samples.testProcesses', 'executedBy', 'witnessUser']),
        ])->render();

        $this->assertStringContainsString('SAKSI SATU', strtoupper($pdfHtml));
        $this->assertStringContainsString('SAKSI DUA', strtoupper($pdfHtml));
        $this->assertStringContainsString('PENYIDIK NRP. 123', strtoupper($pdfHtml));
        $this->assertStringContainsString('KEPALA LAB NIP. 456', strtoupper($pdfHtml));

        $legacyDisposal = SampleDisposal::factory()->create([
            'witness_entries' => null,
            'witness_name' => 'Saksi Legacy',
            'witness_role' => 'Pengawas Legacy',
            'witness_user_id' => null,
        ]);

        $legacyPdfHtml = view('pdf.berita-acara-pemusnahan', [
            'disposal' => $legacyDisposal->load(['samples.testRequest.investigator', 'samples.testProcesses', 'executedBy', 'witnessUser']),
        ])->render();

        $this->assertStringContainsString('SAKSI LEGACY', strtoupper($legacyPdfHtml));
        $this->assertStringContainsString('KEPALA FARMAPOL', strtoupper($legacyPdfHtml));
    }

    public function test_pdf_view_renders_manual_approver_identity_for_kepala_farmapol(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'approver_name' => 'Kombes Manual',
            'approver_role' => 'KBP',
            'approver_identity' => '12345678',
        ]);

        $pdfHtml = view('pdf.berita-acara-pemusnahan', [
            'disposal' => $disposal->load(['samples.testRequest.investigator', 'samples.testProcesses', 'executedBy', 'witnessUser']),
        ])->render();

        $this->assertStringContainsString('KOMBES MANUAL', strtoupper($pdfHtml));
        $this->assertStringContainsString('KBP NRP. 12345678', strtoupper($pdfHtml));
    }

    public function test_disposal_show_displays_details(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'witness_name' => 'Test Witness',
            'witness_user_id' => null,
            'witness_entries' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Test Witness');
        $response->assertSee($disposal->batch_number);
    }

    public function test_disposal_show_displays_multiple_witnesses(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'witness_entries' => [
                ['name' => 'Saksi Satu', 'role' => 'Penyidik', 'identity' => '123'],
                ['name' => 'Saksi Dua', 'role' => 'Kepala Lab', 'identity' => '456'],
            ],
            'witness_name' => 'Saksi Satu',
            'witness_role' => 'Penyidik',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Saksi Satu');
        $response->assertSee('Saksi Dua');
    }

    public function test_disposal_show_displays_manual_approver(): void
    {
        $disposal = SampleDisposal::factory()->create([
            'approver_name' => 'Irjen Manual',
            'approver_role' => 'KBP',
            'approver_identity' => 'NRP. 12345678',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Irjen Manual');
        $response->assertSee('KBP NRP. 12345678');
    }

    public function test_disposal_show_uses_test_request_suspect_name(): void
    {
        $sample = $this->createEligibleSampleForDisposal(lhuNumber: 'LHU-SHOW-0001');
        $disposal = SampleDisposal::factory()->create();

        $sample->markAsDisposed($disposal);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee($sample->testRequest->suspect_name);
    }

    public function test_sample_scope_eligible_for_disposal_includes_pending_production_samples(): void
    {
        $sample = $this->createEligibleSampleForDisposal(
            disposalStatus: SampleDisposalStatus::PENDING,
            lhuNumber: 'LHU-2025-0090'
        );

        $this->assertTrue(Sample::eligibleForDisposal()->get()->contains($sample));
    }

    public function test_sample_scope_eligible_for_disposal_uses_configured_retention_days(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'inventory.disposal_retention_days'],
            ['value' => 0]
        );

        $sample = $this->createEligibleSampleForDisposal(daysAgo: 1, lhuNumber: 'LHU-2025-0091');

        $this->assertTrue(Sample::eligibleForDisposal()->get()->contains($sample));
    }

    public function test_sample_scope_eligible_for_disposal_defaults_to_90_days_when_setting_missing(): void
    {
        SystemSetting::where('key', 'inventory.disposal_retention_days')->delete();

        $sample = $this->createEligibleSampleForDisposal(daysAgo: 30, lhuNumber: 'LHU-2025-0092');

        $this->assertFalse(Sample::eligibleForDisposal()->get()->contains($sample));
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
                'witnesses' => [
                    ['user_id' => '', 'name' => 'Saksi', 'role' => 'Petugas'],
                ],
            ]);

        $response->assertRedirect(route('inventory.disposal.index'));
        $response->assertSessionHas('error');
    }

    private function createEligibleSampleForDisposal(
        int $daysAgo = 91,
        SampleDisposalStatus $disposalStatus = SampleDisposalStatus::PENDING,
        string $lhuNumber = 'LHU-2025-0001'
    ): Sample {
        $testRequest = TestRequest::factory()->create([
            'suspect_name' => 'Tersangka '.$lhuNumber,
        ]);
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
