<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use App\Services\AI\AiCommsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery\MockInterface;
use Tests\TestCase;

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
                ->with('Test prompt', null, 'general', [])
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

    public function test_compose_passes_variables_to_service()
    {
        $this->mock(AiCommsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateMessage')
                ->once()
                ->with('Test prompt', null, 'general', ['nama', 'nomor surat'])
                ->andReturn('Generated response');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', [
                'prompt' => 'Test prompt',
                'variables' => ['nama', 'bad*', '{nomor surat}', 'nama'],
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
