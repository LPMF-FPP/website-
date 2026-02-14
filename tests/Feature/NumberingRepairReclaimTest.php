<?php

namespace Tests\Feature;

use App\Models\EvidenceUnit;
use App\Models\Investigator;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\Sequence;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRepairReclaimTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingRepairService $repairService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repairService = app(NumberingRepairService::class);

        // Setup default numbering settings using settings_fake
        settings_fake([
            'numbering.sample_code.pattern' => 'LS{SEQ:3}I{YYYY}',
            'numbering.sample_code.reset' => 'yearly',
            'numbering.ba.pattern' => 'BA-{SEQ:3}/{RM}/{YYYY}',
            'numbering.ba.reset' => 'yearly',
            'numbering.tracking.pattern' => 'TR-{SEQ:3}I{YYYY}',
            'numbering.tracking.reset' => 'yearly',
        ]);
    }

    public function test_it_detects_reclaimable_gap_when_last_document_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'to_office' => 'Kejaksaan',
            'case_description' => 'Test Case',
        ]);

        // Create 3 samples: LS001, LS002, LS003
        $samples = [];
        for ($i = 0; $i < 3; $i++) {
            $samples[] = Sample::create([
                'test_request_id' => $request->id,
                'short_description' => 'Sample '.($i + 1),
                'sample_form' => 'pill',
                'sample_category' => 'narkotika',
            ]);
        }

        // We want to simulate a gap where LS002 is missing but LS003 exists
        // And the counter is at 3 (LS003)
        // Since we now have auto-rollback on delete, simply deleting LS002 won't create a gap if it was the last one
        // But deleting the middle one (LS002) will NOT rollback the counter (because it's not the last)

        // Delete LS002 (middle sample)
        $samples[1]->delete();

        // Now we have: LS001, LS003 (gap at LS002), counter = 3
        // BUT reclaim only works if gap is at (counter - 1).
        // Here counter=3, gap=2 (which is 3-1). So it SHOULD be reclaimable!

        $result = $this->repairService->canReclaimGap('sample_code');

        $this->assertNotNull($result);
        $this->assertTrue($result['can_reclaim']);
        $this->assertEquals(2, $result['gap_position']);
        $this->assertEquals('LS003I'.now()->year, $result['document_to_rename']['current_number']);
        $this->assertEquals('LS002I'.now()->year, $result['document_to_rename']['new_number']);
    }

    public function test_it_cannot_reclaim_gap_in_middle_of_sequence_if_not_last_gap()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'to_office' => 'Kejaksaan',
            'case_description' => 'Test Case',
        ]);

        // Create 4 samples: LS001, LS002, LS003, LS004
        $samples = [];
        for ($i = 0; $i < 4; $i++) {
            $samples[] = Sample::create([
                'test_request_id' => $request->id,
                'short_description' => 'Sample '.($i + 1),
                'sample_form' => 'pill',
                'sample_category' => 'narkotika',
            ]);
        }

        // Delete LS002 to create a gap
        $samples[1]->delete();

        // Now: LS001, LS003, LS004. Counter = 4.
        // Gap at 2.
        // Last doc is 4.
        // Gap (2) != Max (4) - 1. So cannot reclaim.

        $result = $this->repairService->canReclaimGap('sample_code');

        $this->assertNotNull($result);
        $this->assertFalse($result['can_reclaim']);
        $this->assertEquals(2, $result['gaps'][0]);
    }

    public function test_it_executes_reclaim_and_updates_counter()
    {
        $investigator = Investigator::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'to_office' => 'Kejaksaan',
            'case_description' => 'Test Case',
        ]);

        // Create 2 samples
        $sample1 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 1',
            'sample_form' => 'pill',
            'sample_category' => 'narkotika',
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 2',
            'sample_form' => 'pill',
            'sample_category' => 'narkotika',
        ]);

        // Delete sample 1 to create gap at position 1
        // Counter is 2. Gap is 1. (2-1=1). Reclaimable!
        $sample1->delete();

        // Now: only LS002 exists, counter = 2, gap at 1
        // Reclaim should: rename LS002 → LS001, counter 2 → 1

        $result = $this->repairService->reclaimGap('sample_code', 'Test reclaim');

        $this->assertTrue($result['success']);

        // Verify sample was renamed
        $sample2->refresh();
        $this->assertStringContainsString('LS001', $sample2->sample_code);

        // Verify counter was updated
        $sequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(1, $sequence->current_value);
    }

    public function test_it_cascades_reclaim_rename_to_remaining_units(): void
    {
        $investigator = Investigator::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'to_office' => 'Kejaksaan',
            'case_description' => 'Test Case',
        ]);

        $sample1 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 1',
            'sample_form' => 'pill',
            'sample_category' => 'narkotika',
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 2',
            'sample_form' => 'pill',
            'sample_category' => 'narkotika',
        ]);

        $this->assertSame('LS001I'.now()->year, $sample1->sample_code);
        $this->assertSame('LS002I'.now()->year, $sample2->sample_code);

        $evidenceUnit = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample2->id,
            'sample_code' => $sample2->sample_code,
            'receipt_code' => 'TR-LPMF001I'.now()->year,
            'sample_type' => 'Tablet',
        ]);
        $remaining = RemainingUnit::create([
            'evidence_unit_id' => $evidenceUnit->id,
            'sample_code' => $sample2->sample_code,
            'remaining_code' => $sample2->sample_code.'-SISA',
        ]);

        $sample1->delete();

        $result = $this->repairService->reclaimGap('sample_code', 'Test reclaim');
        $this->assertTrue($result['success']);

        $sample2->refresh();
        $remaining->refresh();

        $this->assertSame('LS001I'.now()->year, $sample2->sample_code);
        $this->assertSame('LS001I'.now()->year, $remaining->sample_code);
        $this->assertSame('LS001I'.now()->year.'-SISA', $remaining->remaining_code);
    }
}
