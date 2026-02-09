<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Services\AI\AiCommsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\User;

class AiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Grant the permission required by the route middleware
        Gate::define('manage-settings', function () {
            return true;
        });
    }

    public function test_compose_success()
    {
        $this->mock(AiCommsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateMessage')
                ->once()
                ->with('Test prompt', null)
                ->andReturn('Generated response');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', [
                'prompt' => 'Test prompt',
            ]);

        $response->assertStatus(200)
            ->assertJson(['result' => 'Generated response']);
    }

    public function test_compose_validation_error()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_compose_service_error()
    {
        $this->mock(AiCommsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateMessage')
                ->andThrow(new \RuntimeException('Service failure'));
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', [
                'prompt' => 'Test prompt',
            ]);

        $response->assertStatus(500)
            ->assertJson(['error' => 'Failed to generate message.']);
    }
}
