<?php

namespace Tests\Feature\Integration;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RequestToDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_complete_request_processing_workflow(): void
    {
        $this->markTestSkipped('API routes for requests POST/PATCH not implemented yet');
    }

    public function test_settings_affect_request_numbering(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'numbering' => [
                'lhu' => [
                    'prefix' => 'TEST',
                    'separator' => '/',
                    'year_format' => 'YY',
                ],
            ],
        ]);

        $response->assertSuccessful();
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
        $this->markTestSkipped('API route for PATCH requests not implemented yet');
    }
}
