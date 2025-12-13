<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DatabaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $unauthorizedUser;
    protected TestRequest $testRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create unauthorized user
        $this->unauthorizedUser = User::factory()->create([
            'role' => 'guest', // Role not in allowed list
        ]);

        // Create test request
        $this->testRequest = TestRequest::factory()->create();
    }

    /** @test */
    public function test_unauthorized_user_cannot_access_database_index()
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('database.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function test_authorized_user_can_access_database_index()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function test_input_validation_rejects_invalid_status()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index', [
                'status' => 'invalid_status',
            ]));

        $response->assertSessionHasErrors('status');
    }

    /** @test */
    public function test_input_validation_rejects_invalid_date_range()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index', [
                'date_from' => '2025-12-31',
                'date_to' => '2025-01-01', // Earlier than date_from
            ]));

        $response->assertSessionHasErrors('date_to');
    }

    /** @test */
    public function test_input_validation_accepts_valid_filters()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index', [
                'status' => 'completed',
                'tipe' => 'generate',
                'date_from' => '2025-01-01',
                'date_to' => '2025-12-31',
            ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function test_path_traversal_is_blocked_in_download()
    {
        $maliciousPath = '../../../config/database.php';
        
        $url = URL::signedRoute('database.docs.download.generated', [
            'generated' => 1,
            'file_path' => $maliciousPath,
            'filename' => 'database.php',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get($url);

        $response->assertStatus(403); // Should be blocked
    }

    /** @test */
    public function test_path_traversal_is_blocked_in_preview()
    {
        $maliciousPath = '../../../.env';
        
        $url = URL::signedRoute('database.docs.preview.generated', [
            'generated' => 1,
            'file_path' => $maliciousPath,
            'filename' => '.env',
            'mime_type' => 'text/plain',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get($url);

        $response->assertStatus(403); // Should be blocked
    }

    /** @test */
    public function test_pagination_works_correctly()
    {
        // Create 60 test requests (more than perPage = 50)
        TestRequest::factory()->count(60)->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index'));

        $response->assertStatus(200);
        
        // Check that pagination is present
        $data = $response->viewData('results');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $data);
        $this->assertEquals(50, $data->perPage());
        $this->assertGreaterThan(1, $data->lastPage());
    }

    /** @test */
    public function test_query_length_limit_is_enforced()
    {
        $longQuery = str_repeat('a', 501); // Exceeds max:500

        $response = $this->actingAs($this->adminUser)
            ->get(route('database.index', [
                'q' => $longQuery,
            ]));

        $response->assertSessionHasErrors('q');
    }

    /** @test */
    public function test_suggest_endpoint_uses_safe_queries()
    {
        // Test that suggest endpoint doesn't fail with special characters
        $specialChars = "' OR '1'='1"; // SQL injection attempt

        $response = $this->actingAs($this->adminUser)
            ->get(route('database.suggest', [
                'q' => $specialChars,
            ]));

        $response->assertStatus(200);
        $response->assertJson(['items' => []]);
    }

    /** @test */
    public function test_document_download_requires_signed_url()
    {
        $document = Document::factory()->create([
            'test_request_id' => $this->testRequest->id,
        ]);

        // Try to access without signed URL
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.docs.download', ['doc' => $document->id]));

        $response->assertStatus(403); // Should require signed URL
    }

    /** @test */
    public function test_bundle_download_works_for_authorized_user()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('database.request.bundle', [
                'testRequest' => $this->testRequest->id,
            ]));

        // Should return 404 if no documents, not 403
        $response->assertStatus(404);
    }
}
