<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiComposeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set config for AI service
        Config::set('services.ai.base_url', 'https://api.openai.com/v1');
        Config::set('services.ai.key', 'test-key');
        Config::set('services.ai.model', 'gpt-3.5-turbo');
    }

    /**
     * Test Case 1: Compose Success
     *
     * @return void
     */
    public function test_compose_success()
    {
        // User must be admin to pass 'manage-settings' gate
        $user = User::factory()->create(['role' => 'admin']);

        // Mock success response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is a mocked AI response.',
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($mockResponse, 200),
            '*' => Http::response([], 200), // Catch-all
        ]);

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', [
                'prompt' => 'Hello',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'result' => 'This is a mocked AI response.',
            ]);
    }

    /**
     * Test Case 2: Validation Error
     *
     * @return void
     */
    public function test_validation_error()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    /**
     * Test Case 3: API Error Handling
     *
     * @return void
     */
    public function test_api_error_handling()
    {
        $user = User::factory()->create(['role' => 'admin']);

        Http::fake([
            'https://api.openai.com/v1/*' => Http::response('Internal Server Error', 500),
            '*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/whatsapp/ai/compose', [
                'prompt' => 'Hello',
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'Layanan AI sedang tidak tersedia. Silakan coba lagi.',
                'code' => 'AI_SERVICE_UNAVAILABLE',
            ])
            ->assertJsonStructure(['request_id']);
    }
}
