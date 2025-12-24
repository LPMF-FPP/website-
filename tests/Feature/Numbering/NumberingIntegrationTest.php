<?php

namespace Tests\Feature\Numbering;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingService;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_sample_uses_numbering_service(): void
    {
        $investigator = \App\Models\Investigator::factory()->create();
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $sample = Sample::create([
            'test_request_id' => $testRequest->id,
            'investigator_id' => $investigator->id,
            'sample_name' => 'Test Sample',
            'matrix_type' => 'solid',
            'sample_type' => 'drug',
            'material' => 'Test Material',
            'description' => 'Test Description',
            'status' => 'received',
            // sample_code should be auto-generated
        ]);

        $this->assertNotNull($sample->sample_code);
        $this->assertIsString($sample->sample_code);
        $this->assertNotEmpty($sample->sample_code);
    }

    public function test_sample_generates_sequential_codes(): void
    {
        $investigator = \App\Models\Investigator::factory()->create();
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $sample1 = Sample::create([
            'test_request_id' => $testRequest->id,
            'investigator_id' => $investigator->id,
            'sample_name' => 'Sample 1',
            'matrix_type' => 'solid',
            'sample_type' => 'drug',
            'material' => 'Material 1',
            'description' => 'Test',
            'status' => 'received',
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $testRequest->id,
            'investigator_id' => $investigator->id,
            'sample_name' => 'Sample 2',
            'matrix_type' => 'solid',
            'sample_type' => 'drug',
            'material' => 'Material 2',
            'description' => 'Test',
            'status' => 'received',
        ]);

        $this->assertNotEquals($sample1->sample_code, $sample2->sample_code);
        $this->assertNotEmpty($sample1->sample_code);
        $this->assertNotEmpty($sample2->sample_code);
    }

    public function test_test_request_uses_numbering_service(): void
    {
        $investigator = \App\Models\Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $this->assertNotNull($request->request_number);
        $this->assertIsString($request->request_number);
        $this->assertNotEmpty($request->request_number);
    }

    public function test_test_request_generates_sequential_numbers(): void
    {
        $investigator = \App\Models\Investigator::factory()->create();
        $user = User::factory()->create();

        $request1 = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $request2 = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $this->assertNotEquals($request1->request_number, $request2->request_number);
        $this->assertNotEmpty($request1->request_number);
        $this->assertNotEmpty($request2->request_number);
    }

    public function test_numbering_service_preview_works(): void
    {
        $numbering = app(NumberingService::class);

        $preview = $numbering->preview('sample_code');

        $this->assertIsString($preview);
        $this->assertNotEmpty($preview);
    }

    public function test_numbering_service_current_snapshot_works(): void
    {
        $numbering = app(NumberingService::class);

        $snapshot = $numbering->currentSnapshot('sample_code');

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('current', $snapshot);
        $this->assertArrayHasKey('next', $snapshot);
        $this->assertArrayHasKey('pattern', $snapshot);

        // current can be null if no numbers issued yet
        $this->assertIsString($snapshot['next']);
        $this->assertIsString($snapshot['pattern']);
    }

    public function test_all_scopes_have_valid_configuration(): void
    {
        $numbering = app(NumberingService::class);
        $scopes = ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'];

        foreach ($scopes as $scope) {
            $snapshot = $numbering->currentSnapshot($scope);

            $this->assertIsArray($snapshot, "Scope {$scope} should return array");
            $this->assertArrayHasKey('next', $snapshot, "Scope {$scope} should have 'next' key");
            $this->assertIsString($snapshot['next'], "Scope {$scope} next should be string");
            $this->assertNotEmpty($snapshot['next'], "Scope {$scope} next should not be empty");
        }
    }
}
