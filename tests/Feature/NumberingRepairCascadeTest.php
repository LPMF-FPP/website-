<?php

namespace Tests\Feature;

use App\Models\EvidenceUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRepairCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_sample_code_cascades_to_evidence_units(): void
    {
        // Authenticate a user for audit logging
        $user = User::factory()->create();
        $this->actingAs($user);

        // Arrange: Create test request, sample, and evidence unit
        $request = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'LS073I2026',
        ]);

        // Create evidence unit directly (no factory)
        $evidenceUnit = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample->id,
            'sample_code' => 'LS073I2026',
            'receipt_code' => 'TR-LPMF001I2026',
            'sample_type' => 'Tablet',
            'sample_desc' => 'Test sample',
        ]);

        $service = app(NumberingRepairService::class);

        // Act: Edit sample code via repair service
        $result = $service->editNumber('sample_code', $sample->id, 'LS072I2026', 'Reclaim skipped number');

        // Assert: Both sample and evidence_unit should be updated
        $this->assertTrue($result['success']);
        $this->assertEquals('LS072I2026', $sample->fresh()->sample_code);
        $this->assertEquals('LS072I2026', $evidenceUnit->fresh()->sample_code, 'Evidence unit sample_code should cascade update');
    }

    public function test_edit_sample_code_only_updates_matching_evidence_units(): void
    {
        // Authenticate a user for audit logging
        $user = User::factory()->create();
        $this->actingAs($user);

        // Arrange: Create test request with multiple samples
        $request = TestRequest::factory()->create();

        $sample1 = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'LS073I2026',
        ]);

        $sample2 = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'LS074I2026',
        ]);

        $evidenceUnit1 = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample1->id,
            'sample_code' => 'LS073I2026',
            'receipt_code' => 'TR-LPMF001I2026',
            'sample_type' => 'Tablet',
        ]);

        $evidenceUnit2 = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample2->id,
            'sample_code' => 'LS074I2026',
            'receipt_code' => 'TR-LPMF002I2026',
            'sample_type' => 'Powder',
        ]);

        $service = app(NumberingRepairService::class);

        // Act: Edit only sample1's code
        $result = $service->editNumber('sample_code', $sample1->id, 'LS072I2026', 'Reclaim skipped number');

        // Assert: Only sample1 and its evidence_unit should be updated
        $this->assertTrue($result['success']);
        $this->assertEquals('LS072I2026', $sample1->fresh()->sample_code);
        $this->assertEquals('LS072I2026', $evidenceUnit1->fresh()->sample_code);

        // sample2 and its evidence_unit should remain unchanged
        $this->assertEquals('LS074I2026', $sample2->fresh()->sample_code);
        $this->assertEquals('LS074I2026', $evidenceUnit2->fresh()->sample_code);
    }
}
