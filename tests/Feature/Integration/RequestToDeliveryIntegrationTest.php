<?php

namespace Tests\Feature\Integration;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RequestToDeliveryIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_complete_request_processing_workflow(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/requests', [
            'investigator_id' => 1,
            'request_letter_number' => 'REQ-INT-001',
            'case_title' => 'Integration Test Case',
            'samples' => [
                ['name' => 'Sample A', 'quantity' => 1, 'unit' => 'kg'],
            ],
        ]);

        $response->assertStatus(201);
        $request = TestRequest::where('request_letter_number', 'REQ-INT-001')->first();

        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);

        $response = $this->actingAs($user)->patchJson("/api/requests/{$request->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();
        $request->refresh();
        $this->assertEquals('in_progress', $request->status);

        $response = $this->actingAs($user)->patchJson("/api/requests/{$request->id}", [
            'status' => 'ready_for_delivery',
        ]);

        $response->assertOk();
        $request->refresh();
        $this->assertEquals('ready_for_delivery', $request->status);
    }

    public function test_settings_affect_request_numbering(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->putJson('/api/settings', [
            'numbering' => [
                'lhu' => [
                    'prefix' => 'TEST',
                    'separator' => '/',
                    'year_format' => 'YY',
                ],
            ],
        ], ['Authorization' => "Bearer {$user->createToken('test')->plainTextToken}"]);

        settings_forget_cache();

        $this->assertEquals('TEST', settings('numbering.lhu.prefix'));
    }

    public function test_request_sample_relationship(): void
    {
        $request = TestRequest::factory()->create();
        $samples = Sample::factory()->count(3)->create([
            'test_request_id' => $request->id,
        ]);

        $this->assertCount(3, $request->samples);
        $this->assertEquals($request->id, $samples->first()->test_request_id);
    }

    public function test_request_status_transitions_are_logged(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($user)->patchJson("/api/requests/{$request->id}", [
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => TestRequest::class,
            'subject_id' => $request->id,
            'description' => 'updated',
        ]);
    }
}
