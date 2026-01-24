<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRepairExtractSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingRepairService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NumberingRepairService::class);
    }

    protected function extractSequenceNumber(string $scope, $document): ?int
    {
        $method = new \ReflectionMethod($this->service, 'extractSequenceNumber');
        $method->setAccessible(true);

        return $method->invoke($this->service, $scope, $document);
    }

    // ==================== SAMPLE_CODE TESTS ====================

    public function test_extract_sequence_from_ls_format(): void
    {
        $sample = Sample::factory()->create(['sample_code' => 'LS072I2026']);
        $result = $this->extractSequenceNumber('sample_code', $sample);
        $this->assertEquals(72, $result);
    }

    public function test_extract_sequence_from_ls_single_digit(): void
    {
        $sample = Sample::factory()->create(['sample_code' => 'LS001I2026']);
        $result = $this->extractSequenceNumber('sample_code', $sample);
        $this->assertEquals(1, $result);
    }

    public function test_extract_sequence_from_single_letter_prefix(): void
    {
        $sample = Sample::factory()->create(['sample_code' => 'W003I2026']);
        $result = $this->extractSequenceNumber('sample_code', $sample);
        $this->assertEquals(3, $result);
    }

    public function test_extract_sequence_does_not_match_year(): void
    {
        $sample = Sample::factory()->create(['sample_code' => 'LS072I2026']);
        $result = $this->extractSequenceNumber('sample_code', $sample);
        // Should NOT return 2026 (year)
        $this->assertNotEquals(2026, $result);
        $this->assertEquals(72, $result);
    }

    // ==================== TRACKING TESTS ====================

    public function test_extract_sequence_from_tr_lpmf_format(): void
    {
        $request = TestRequest::factory()->create(['receipt_number' => 'TR-LPMF024I2026']);
        $result = $this->extractSequenceNumber('tracking', $request);
        $this->assertEquals(24, $result);
    }

    public function test_extract_sequence_from_tr_lpmf_low_number(): void
    {
        $request = TestRequest::factory()->create(['receipt_number' => 'TR-LPMF001I2026']);
        $result = $this->extractSequenceNumber('tracking', $request);
        $this->assertEquals(1, $result);
    }

    // ==================== BA TESTS ====================

    public function test_extract_sequence_from_ba_rim_format(): void
    {
        $request = TestRequest::factory()->create(['request_number' => 'BA-RIM/024/I/2026/FPP']);
        $result = $this->extractSequenceNumber('ba', $request);
        $this->assertEquals(24, $result);
    }

    public function test_extract_sequence_from_ba_rim_low_number(): void
    {
        $request = TestRequest::factory()->create(['request_number' => 'BA-RIM/001/I/2026/FPP']);
        $result = $this->extractSequenceNumber('ba', $request);
        $this->assertEquals(1, $result);
    }

    // ==================== LHU TESTS ====================

    public function test_extract_sequence_from_lhu_bb_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = TestRequest::factory()->create();
        $sample = Sample::factory()->create(['test_request_id' => $request->id]);

        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'metadata' => ['lhu_number' => 'LHU-BB/026/I/2026/FPP'],
        ]);

        $result = $this->extractSequenceNumber('lhu', $process);
        $this->assertEquals(26, $result);
    }

    // ==================== BA_PENYERAHAN TESTS ====================

    public function test_extract_sequence_from_ba_st_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = TestRequest::factory()->create();

        $document = Document::factory()->create([
            'document_type' => 'ba_penyerahan',
            'test_request_id' => $request->id,
            'filename' => 'BA-ST-006-I-2026-FPP-ba-penyerahan.pdf',
        ]);

        $result = $this->extractSequenceNumber('ba_penyerahan', $document);
        $this->assertEquals(6, $result);
    }

    public function test_extract_sequence_from_ba_st_low_number(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = TestRequest::factory()->create();

        $document = Document::factory()->create([
            'document_type' => 'ba_penyerahan',
            'test_request_id' => $request->id,
            'filename' => 'BA-ST-001-I-2026-FPP-ba-penyerahan.pdf',
        ]);

        $result = $this->extractSequenceNumber('ba_penyerahan', $document);
        $this->assertEquals(1, $result);
    }
}
