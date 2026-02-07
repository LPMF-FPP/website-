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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SampleDisposalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
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
        // Create a sample with completed LHU older than 90 days
        $testRequest = TestRequest::factory()->create();
        $eligibleSample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        // Create completed test process with LHU number, 90+ days ago
        SampleTestProcess::factory()->create([
            'sample_id' => $eligibleSample->id,
            'stage' => 'interpretation',
            'completed_at' => now()->subDays(91),
            'metadata' => ['lhu_number' => 'LHU-2025-0001'],
        ]);

        // Create a non-eligible sample (no LHU, recent)
        $nonEligibleSample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'disposal_status' => SampleDisposalStatus::PENDING,
        ]);

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
        // Create eligible sample
        $sample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.index'));

        $response->assertStatus(200);
        $response->assertSee($sample->sample_code);
    }

    public function test_disposal_create_shows_form(): void
    {
        // Create eligible samples first
        $samples = Sample::factory()->count(2)->create([
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.create', [
                'sample_ids' => $samples->pluck('id')->join(','),
            ]));

        $response->assertStatus(200);
        $response->assertSee('Metode Pemusnahan');
        $response->assertSee('Nama Saksi');
    }

    public function test_can_execute_batch_disposal(): void
    {
        // Create eligible samples
        $samples = Sample::factory()->count(3)->create([
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => $samples->pluck('id')->toArray(),
                'method' => 'bakar',
                'witness_name' => 'Budi Santoso',
                'witness_role' => 'Kepala Lab',
                'notes' => 'Pemusnahan berjalan lancar',
            ]);

        $response->assertRedirect();

        // Verify disposal record created
        $this->assertDatabaseHas('sample_disposals', [
            'method' => 'bakar',
            'witness_name' => 'Budi Santoso',
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
        $sample = Sample::factory()->create([
            'disposal_status' => SampleDisposalStatus::ELIGIBLE,
        ]);

        $this->actingAs($this->user)
            ->post(route('inventory.disposal.store'), [
                'sample_ids' => [$sample->id],
                'method' => 'hancur',
                'witness_name' => 'Test Witness',
                'witness_role' => 'Test Role',
            ]);

        $disposal = SampleDisposal::latest()->first();

        $this->assertNotNull($disposal);
        $this->assertEquals($this->user->id, $disposal->executed_by);
        $this->assertEquals($this->user->id, $disposal->created_by);
        $this->assertNotNull($disposal->executed_at);
    }

    public function test_can_download_berita_acara_pdf(): void
    {
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
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.disposal.show', $disposal));

        $response->assertStatus(200);
        $response->assertSee('Test Witness');
        $response->assertSee($disposal->batch_number);
    }
}
