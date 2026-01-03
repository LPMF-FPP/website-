<?php

namespace Tests\Feature\Api\Settings;

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\Sample;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\IkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IkuSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->regularUser = User::factory()->create(['role' => 'analyst']);
    }

    // ============================================
    // GET /api/settings/iku Tests
    // ============================================

    public function test_can_get_iku_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/iku');

        $response->assertOk()
            ->assertJsonStructure([
                'iku' => [
                    'enabled',
                    'period_mode',
                    'weights' => ['registration', 'lab_exam', 'report', 'survey'],
                    'target_samples_by_year',
                    'sources' => ['A', 'B', 'C', 'E'],
                    'survey_required_for_delivery',
                ],
            ]);
    }

    public function test_get_iku_settings_returns_defaults_when_not_configured(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/iku');

        $response->assertOk();

        $iku = $response->json('iku');

        // Check default weights
        $this->assertEquals(10, $iku['weights']['registration']);
        $this->assertEquals(40, $iku['weights']['lab_exam']);
        $this->assertEquals(40, $iku['weights']['report']);
        $this->assertEquals(10, $iku['weights']['survey']);

        // Check default period mode
        $this->assertEquals('monthly', $iku['period_mode']);

        // Check default enabled
        $this->assertTrue($iku['enabled']);
    }

    public function test_get_iku_settings_requires_authentication(): void
    {
        $response = $this->getJson('/api/settings/iku');

        $response->assertUnauthorized();
    }

    public function test_get_iku_settings_requires_manage_settings_permission(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/settings/iku');

        $response->assertForbidden();
    }

    // ============================================
    // PUT /api/settings/iku Tests
    // ============================================

    public function test_can_update_iku_settings(): void
    {
        $payload = [
            'enabled' => true,
            'period_mode' => 'yearly',
            'weights' => [
                'registration' => 15,
                'lab_exam' => 35,
                'report' => 35,
                'survey' => 15,
            ],
            'target_samples_by_year' => [
                '2025' => 400,
                '2026' => 500,
            ],
            'survey_required_for_delivery' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', $payload);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Pengaturan IKU berhasil disimpan.',
            ]);

        // Verify settings were saved
        $this->assertEquals('yearly', SystemSetting::where('key', 'iku.period_mode')->first()?->value);
        $this->assertEquals(15, SystemSetting::where('key', 'iku.weights.registration')->first()?->value);
        $this->assertEquals(35, SystemSetting::where('key', 'iku.weights.lab_exam')->first()?->value);
    }

    public function test_update_iku_settings_validates_weights_sum_to_100(): void
    {
        $payload = [
            'weights' => [
                'registration' => 10,
                'lab_exam' => 40,
                'report' => 40,
                'survey' => 20, // Sum = 110, should fail
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['weights']);
    }

    public function test_update_iku_settings_accepts_valid_weights(): void
    {
        $payload = [
            'weights' => [
                'registration' => 25,
                'lab_exam' => 25,
                'report' => 25,
                'survey' => 25, // Sum = 100, should pass
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', $payload);

        $response->assertOk();
    }

    public function test_update_iku_settings_validates_period_mode(): void
    {
        $payload = [
            'period_mode' => 'weekly', // Invalid
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['period_mode']);
    }

    public function test_update_iku_settings_validates_target_samples_positive(): void
    {
        $payload = [
            'target_samples_by_year' => [
                '2025' => 0, // Invalid - must be positive
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', $payload);

        $response->assertUnprocessable();
    }

    public function test_update_iku_settings_requires_authentication(): void
    {
        $response = $this->putJson('/api/settings/iku', [
            'enabled' => false,
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_iku_settings_requires_authorization(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->putJson('/api/settings/iku', [
                'enabled' => false,
            ]);

        $response->assertForbidden();
    }

    public function test_partial_update_only_changes_specified_fields(): void
    {
        // First, set initial values
        SystemSetting::updateOrCreate(['key' => 'iku.enabled'], ['value' => true]);
        SystemSetting::updateOrCreate(['key' => 'iku.period_mode'], ['value' => 'monthly']);

        // Update only period_mode
        $response = $this->actingAs($this->admin)
            ->putJson('/api/settings/iku', [
                'period_mode' => 'yearly',
            ]);

        $response->assertOk();

        // Check period_mode was updated
        $this->assertEquals('yearly', SystemSetting::where('key', 'iku.period_mode')->first()?->value);

        // enabled should remain unchanged
        $this->assertTrue((bool) SystemSetting::where('key', 'iku.enabled')->first()?->value);
    }

    // ============================================
    // GET /api/settings/iku/preview Tests
    // ============================================

    public function test_can_get_iku_preview(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/iku/preview');

        $response->assertOk()
            ->assertJsonStructure([
                'ok',
                'iku' => [
                    'iku_value',
                    'iku_category',
                    'components' => ['R', 'P', 'L', 'S'],
                    'indexes' => ['registration', 'lab_exam', 'report', 'survey'],
                    'raw_counts' => ['A', 'B', 'C', 'D', 'E', 'F'],
                    'weights',
                    'period' => ['start', 'end'],
                ],
            ]);
    }

    public function test_iku_preview_returns_valid_range(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/iku/preview');

        $response->assertOk();

        $ikuValue = $response->json('iku.iku_value');

        // IKU should be between 0 and 5
        $this->assertGreaterThanOrEqual(0, $ikuValue);
        $this->assertLessThanOrEqual(5, $ikuValue);
    }

    public function test_iku_preview_requires_authentication(): void
    {
        $response = $this->getJson('/api/settings/iku/preview');

        $response->assertUnauthorized();
    }

    // ============================================
    // IKU Service Unit Tests
    // ============================================

    public function test_iku_service_computes_correct_value_with_data(): void
    {
        // Create test data
        $this->createTestData();

        $service = app(IkuService::class);
        $result = $service->computeForPeriod(now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('iku_value', $result);
        $this->assertArrayHasKey('iku_category', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('raw_counts', $result);

        // Verify iku_value is in valid range
        $this->assertGreaterThanOrEqual(0, $result['iku_value']);
        $this->assertLessThanOrEqual(5, $result['iku_value']);

        // Verify category is valid
        $this->assertContains($result['iku_category'], ['A', 'B', 'C', 'D', 'E', 'F']);
    }

    public function test_iku_service_handles_zero_data_gracefully(): void
    {
        $service = app(IkuService::class);
        $result = $service->computeForPeriod(now()->startOfMonth(), now()->endOfMonth());

        // With no data, IKU should be 0
        $this->assertEquals(0, $result['iku_value']);
        $this->assertEquals('F', $result['iku_category']);

        // All raw counts should be 0
        $this->assertEquals(0, $result['raw_counts']['A']);
        $this->assertEquals(0, $result['raw_counts']['B']);
    }

    public function test_iku_service_respects_period_mode_setting(): void
    {
        SystemSetting::updateOrCreate(['key' => 'iku.period_mode'], ['value' => 'yearly']);

        $service = app(IkuService::class);
        $result = $service->computeForCurrentMonth();

        // When period_mode is yearly, period should span the year
        $this->assertEquals(now()->startOfYear()->toDateString(), $result['period']['start']);
        $this->assertEquals(now()->endOfYear()->toDateString(), $result['period']['end']);
    }

    public function test_iku_service_uses_custom_weights(): void
    {
        // Set custom weights
        SystemSetting::updateOrCreate(['key' => 'iku.weights.registration'], ['value' => 25]);
        SystemSetting::updateOrCreate(['key' => 'iku.weights.lab_exam'], ['value' => 25]);
        SystemSetting::updateOrCreate(['key' => 'iku.weights.report'], ['value' => 25]);
        SystemSetting::updateOrCreate(['key' => 'iku.weights.survey'], ['value' => 25]);

        $service = app(IkuService::class);
        $config = $service->getConfig();

        $this->assertEquals(25, $config['weights']['registration']);
        $this->assertEquals(25, $config['weights']['lab_exam']);
        $this->assertEquals(25, $config['weights']['report']);
        $this->assertEquals(25, $config['weights']['survey']);
    }

    public function test_iku_category_mapping(): void
    {
        $service = app(IkuService::class);

        // Test via reflection or by creating data that produces specific IKU values
        // For now, verify the structure
        $result = $service->computeForPeriod(now()->startOfMonth(), now()->endOfMonth());

        $ikuValue = $result['iku_value'];
        $category = $result['iku_category'];

        // Verify category matches expected range
        if ($ikuValue >= 4.5) {
            $this->assertEquals('A', $category);
        } elseif ($ikuValue >= 3.5) {
            $this->assertEquals('B', $category);
        } elseif ($ikuValue >= 2.5) {
            $this->assertEquals('C', $category);
        } elseif ($ikuValue >= 1.5) {
            $this->assertEquals('D', $category);
        } elseif ($ikuValue >= 0.5) {
            $this->assertEquals('E', $category);
        } else {
            $this->assertEquals('F', $category);
        }
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createTestData(): void
    {
        // Create test requests using factory (handles all required fields)
        for ($i = 0; $i < 5; $i++) {
            $status = $i < 3 ? 'completed' : 'submitted';
            $completedAt = $i < 3 ? now() : null;

            $request = TestRequest::factory()->create([
                'status' => $status,
                'submitted_at' => now(),
                'completed_at' => $completedAt,
            ]);

            // Create samples for each request - use valid sample_status values
            Sample::create([
                'test_request_id' => $request->id,
                'sample_code' => 'SMP-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT).'-'.$request->id,
                'short_description' => 'Test Sample '.$i,
                'sample_status' => $i < 2 ? 'delivered' : 'received', // Use valid enum values
                'testing_completed_at' => $i < 2 ? now() : null,
            ]);

            // Create LHU documents
            if ($i < 3) {
                Document::create([
                    'test_request_id' => $request->id,
                    'investigator_id' => $request->investigator_id,
                    'document_type' => 'laporan_hasil_uji',
                    'source' => 'generated',
                    'filename' => 'lhu_'.$i.'_'.$request->id.'.pdf',
                    'original_filename' => 'lhu_'.$i.'_'.$request->id.'.pdf',
                    'file_path' => 'documents/lhu_'.$i.'_'.$request->id.'.pdf',
                ]);
            }

            // Create surveys with all required fields
            if ($i < 2) {
                CustomerSurvey::create([
                    'test_request_id' => $request->id,
                    'respondent_name' => 'Respondent '.$i,
                    'respondent_job_title' => 'Analyst',
                    'respondent_institution' => 'Test Institution',
                    'respondent_job_category' => 'ASN',
                    'request_type' => 'Kimia - Fisika',
                    'voluntary_statement' => true,
                    'answers' => json_encode(['q1' => 4, 'q2' => 5]),
                    'suggestion' => 'Good service',
                    'submitted_at' => now(),
                    'score_avg' => 4.0,
                ]);
            }
        }
    }
}
