<?php

namespace Tests\Feature;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Sequence;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRollbackOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup default numbering settings using settings_fake
        settings_fake([
            'numbering.ba.pattern' => 'BA-{SEQ:3}/{RM}/{YYYY}',
            'numbering.ba.reset' => 'yearly',
            'numbering.tracking.pattern' => 'TR-{SEQ:3}I{YYYY}',
            'numbering.tracking.reset' => 'yearly',
            'numbering.sample_code.pattern' => 'LS{SEQ:3}I{YYYY}',
            'numbering.sample_code.reset' => 'yearly',
        ]);
    }

    public function test_it_rollbacks_ba_and_tracking_when_last_test_request_is_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        // Create test request (will auto-generate BA and tracking numbers)
        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'case_description' => 'Test Case',
        ]);

        // Verify sequences were created
        $baSequence = Sequence::where('scope', 'ba')->first();
        $trackingSequence = Sequence::where('scope', 'tracking')->first();

        $this->assertEquals(1, $baSequence->current_value);
        $this->assertEquals(1, $trackingSequence->current_value);

        // Delete the request
        $request->delete();

        // Verify sequences were rolled back
        $baSequence->refresh();
        $trackingSequence->refresh();

        $this->assertEquals(0, $baSequence->current_value);
        $this->assertEquals(0, $trackingSequence->current_value);
    }

    public function test_it_does_not_rollback_when_deleting_non_last_test_request()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        // Create two test requests
        $request1 = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'suspect_name' => 'John Doe 1',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'case_description' => 'Test Case 1',
        ]);

        $request2 = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'suspect_name' => 'John Doe 2',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'case_description' => 'Test Case 2',
        ]);

        $baSequence = Sequence::where('scope', 'ba')->first();
        $this->assertEquals(2, $baSequence->current_value);

        // Delete the FIRST request (not the last)
        $request1->delete();

        // Sequence should NOT be rolled back (would cause duplicate)
        $baSequence->refresh();
        $this->assertEquals(2, $baSequence->current_value);
    }

    public function test_it_rollbacks_sample_code_when_last_sample_is_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'case_description' => 'Test Case',
        ]);

        // Create sample (will auto-generate sample_code)
        $sample = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Test Sample',
            'sample_form' => 'pill',
            'sample_category' => 'narkotika',
        ]);

        $sampleSequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(1, $sampleSequence->current_value);

        // Delete the sample
        $sample->delete();

        // Verify sequence was rolled back
        $sampleSequence->refresh();
        $this->assertEquals(0, $sampleSequence->current_value);
    }

    public function test_it_does_not_rollback_when_deleting_non_last_sample()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'suspect_name' => 'John Doe',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
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

        $sampleSequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(2, $sampleSequence->current_value);

        // Delete the FIRST sample
        $sample1->delete();

        // Sequence should NOT be rolled back
        $sampleSequence->refresh();
        $this->assertEquals(2, $sampleSequence->current_value);
    }
}
