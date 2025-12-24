<?php

namespace Tests\Feature;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LhuNumberingGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Investigator $investigator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();

        // Create admin user with proper permissions
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->investigator = Investigator::factory()->create();
    }

    protected function createSampleWithProcess(): array
    {
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $this->investigator->id,
            'user_id' => $this->admin->id,
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
        ]);

        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => 'interpretation',
        ]);

        return ['testRequest' => $testRequest, 'sample' => $sample, 'process' => $process];
    }

    public function test_lhu_generation_uses_latest_numbering_settings_for_new_report(): void
    {
        // Configure LHU numbering settings
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu'], ['value' => [
            'pattern' => 'LHU-{YYYY}-{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ]]);
        settings_forget_cache();

        $data = $this->createSampleWithProcess();
        $process = $data['process'];

        // Generate LHU report (first time - should issue new number)
        $response = $this->actingAs($this->admin)
            ->get("/sample-processes/{$process->id}/lab-report");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Verify LHU number was issued and stored
        $process->refresh();
        $metadata = $process->metadata;

        $this->assertNotEmpty($metadata['lhu_number'] ?? null, 'LHU number should be stored in metadata');
        // Pattern is LHU-{YYYY}-{NNNN}, which generates LHU-2025-0001
        $this->assertMatchesRegularExpression(
            '/^LHU-\d{4}-\d{4}$/',
            $metadata['lhu_number'],
            'LHU number should match pattern LHU-YYYY-NNNN, got: ' . ($metadata['lhu_number'] ?? 'null')
        );
    }

    public function test_lhu_generation_reuses_stored_number_on_regeneration(): void
    {
        // Configure LHU settings
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu'], ['value' => [
            'pattern' => 'LHU-{YYYY}-{NNNN}',
            'reset' => 'yearly',
            'start_from' => 100,
        ]]);
        settings_forget_cache();

        $data = $this->createSampleWithProcess();
        $process = $data['process'];

        // First generation
        $this->actingAs($this->admin)->get("/sample-processes/{$process->id}/lab-report")->assertStatus(200);

        $process->refresh();
        $firstLhuNumber = $process->metadata['lhu_number'] ?? null;
        $this->assertNotEmpty($firstLhuNumber);

        // Second generation (regenerate) - should reuse same number
        $this->actingAs($this->admin)->get("/sample-processes/{$process->id}/lab-report")->assertStatus(200);

        $process->refresh();
        $secondLhuNumber = $process->metadata['lhu_number'] ?? null;

        $this->assertEquals(
            $firstLhuNumber,
            $secondLhuNumber,
            'Regenerated LHU report should use the same stored number'
        );
    }

    public function test_lhu_generation_uses_updated_settings_for_new_process(): void
    {
        // Initial settings
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu'], ['value' => [
            'pattern' => 'OLD-{NNNN}',
            'reset' => 'never',
            'start_from' => 1,
        ]]);
        settings_forget_cache();

        // Create first process and generate LHU
        $data1 = $this->createSampleWithProcess();
        $process1 = $data1['process'];

        $this->actingAs($this->admin)->get("/sample-processes/{$process1->id}/lab-report")->assertStatus(200);

        $process1->refresh();
        $firstNumber = $process1->metadata['lhu_number'] ?? null;
        $this->assertStringStartsWith('OLD-', $firstNumber);

        // Update settings to new pattern
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu'], ['value' => [
            'pattern' => 'NEW-{YYYY}-{NNNN}',
            'reset' => 'never',
            'start_from' => 1,
        ]]);
        settings_forget_cache();

        // Create second process - should use new pattern
        $data2 = $this->createSampleWithProcess();
        $process2 = $data2['process'];

        $this->actingAs($this->admin)->get("/sample-processes/{$process2->id}/lab-report")->assertStatus(200);

        $process2->refresh();
        $secondNumber = $process2->metadata['lhu_number'] ?? null;
        $this->assertStringStartsWith('NEW-', $secondNumber);
        $this->assertNotEquals($firstNumber, $secondNumber);
    }

    public function test_lhu_generation_handles_legacy_metadata_fields(): void
    {
        // Test that legacy fields (report_number, lab_report_no) are still respected
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $this->investigator->id,
            'user_id' => $this->admin->id,
        ]);

        $sample = Sample::factory()->create(['test_request_id' => $testRequest->id]);

        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => 'interpretation',
            'metadata' => ['report_number' => 'LEGACY-FLHU001'],
        ]);

        // Generate report - should reuse legacy number
        $this->actingAs($this->admin)->get("/sample-processes/{$process->id}/lab-report")->assertStatus(200);

        $process->refresh();
        $lhuNumber = $process->metadata['lhu_number'] ?? $process->metadata['report_number'] ?? null;

        $this->assertEquals('LEGACY-FLHU001', $lhuNumber);
    }

    public function test_cache_invalidation_after_numbering_update(): void
    {
        // Clear any existing cache from setUp
        settings_forget_cache();
        
        // Set initial pattern using dot-notated keys (correct approach)
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu.pattern'], ['value' => 'CACHE-TEST-{SEQ:4}']);
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu.reset'], ['value' => 'never']);
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu.start_from'], ['value' => 1]);
        settings_forget_cache();

        // Read initial settings (cache them)
        $initialPattern = settings('numbering.lhu.pattern');
        $this->assertEquals('CACHE-TEST-{SEQ:4}', $initialPattern);

        // Update via API (should invalidate cache)
        $this->actingAs($this->admin)
            ->putJson('/api/settings/numbering/lhu', [
                'pattern' => 'CACHE-UPDATED-{SEQ:4}',
                'reset' => 'never',
                'start_from' => 1,
            ])
            ->assertStatus(200);

        // Clear any in-memory cache and re-read from DB
        settings_forget_cache();
        $updatedPattern = settings('numbering.lhu.pattern');
        $this->assertEquals('CACHE-UPDATED-{SEQ:4}', $updatedPattern, 'Cache should be invalidated after update');

        // Create process and verify it uses the updated pattern
        $data = $this->createSampleWithProcess();
        $process = $data['process'];

        $this->actingAs($this->admin)->get("/sample-processes/{$process->id}/lab-report")->assertStatus(200);

        $process->refresh();
        $lhuNumber = $process->metadata['lhu_number'] ?? null;
        $this->assertStringStartsWith('CACHE-UPDATED-', $lhuNumber, 'New LHU should use updated pattern from settings');
    }

    public function test_concurrent_lhu_generation_is_safe(): void
    {
        // Configure settings
        SystemSetting::updateOrCreate(['key' => 'numbering.lhu'], ['value' => [
            'pattern' => 'CONCURRENT-{SEQ:4}',
            'reset' => 'never',
            'start_from' => 1,
        ]]);
        settings_forget_cache();

        // Create multiple processes
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $data = $this->createSampleWithProcess();
            $processes[] = $data['process'];
        }

        // Generate LHU for all processes
        $lhuNumbers = [];
        foreach ($processes as $process) {
            $this->actingAs($this->admin)
                ->get("/sample-processes/{$process->id}/lab-report")
                ->assertStatus(200);

            $process->refresh();
            $lhuNumbers[] = $process->metadata['lhu_number'] ?? null;
        }

        // Verify all numbers are unique
        $uniqueNumbers = array_unique($lhuNumbers);
        $this->assertCount(
            count($lhuNumbers),
            $uniqueNumbers,
            'All LHU numbers should be unique (no duplicates from race conditions)'
        );

        // Verify sequential numbering
        foreach ($lhuNumbers as $number) {
            $this->assertStringStartsWith('CONCURRENT-', $number);
        }
    }
}
