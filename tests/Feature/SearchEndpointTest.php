<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class SearchEndpointTest extends TestCase
{
    /**
     * Test search endpoint returns 200 with valid JSON for authenticated user
     */
    public function test_search_endpoint_returns_200_for_authenticated_user(): void
    {
        // Create authenticated user
        $user = User::first() ?: User::factory()->create();
        $user->update(['email_verified_at' => now()]);

        // Make authenticated request
        $response = $this->actingAs($user)->getJson('/search/data?q=test&doc_type=all&page_people=1&per_page_people=6&page_docs=1&per_page_docs=6');

        // Assert response
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'query',
            'doc_type',
            'sort',
            'summary' => [
                'people_total',
                'documents_total',
            ],
            'people' => [
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'data' => [],
            ],
            'documents' => [
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'data' => [],
            ],
        ]);

        echo "✓ All assertions passed\n";
    }

    /**
     * Test search endpoint returns 401 for unauthenticated user
     */
    public function test_search_endpoint_returns_401_for_unauthenticated_user(): void
    {
        $response = $this->getJson('/search/data?q=test&doc_type=all');
        $response->assertStatus(401);

        echo "✓ 401 assertion passed\n";
    }
}
