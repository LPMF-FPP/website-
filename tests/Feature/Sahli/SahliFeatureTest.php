<?php

namespace Tests\Feature\Sahli;

use App\Livewire\Sahli\Show;
use App\Models\Document;
use App\Models\ExpertWitnessRequest;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\TestResult;
use App\Models\User;
use App\Services\ExpertWitnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SahliFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_submission_stores_private_letter_and_all_milestones(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'admin']);

        $request = app(ExpertWitnessService::class)->createExternal([
            'letter_number' => 'B/123/IX/2026',
            'letter_date' => '2026-09-07',
            'investigator_name' => 'Penyidik Uji',
            'investigator_institution' => 'Instansi Uji',
            'investigator_phone' => '081234567890',
            'submission_token' => (string) Str::uuid(),
        ], UploadedFile::fake()->create('surat-sahli.pdf', 100, 'application/pdf'), $user);

        $this->assertSame(ExpertWitnessRequest::SOURCE_EXTERNAL, $request->source);
        $this->assertCount(5, $request->milestones);
        Storage::disk('local')->assertExists($request->documents->first()->path);
    }

    public function test_external_submission_token_returns_existing_request_on_retry(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'admin']);
        $service = app(ExpertWitnessService::class);
        $token = (string) Str::uuid();
        $data = [
            'letter_number' => 'B/RETRY/2026',
            'letter_date' => '2026-09-07',
            'investigator_name' => 'Penyidik Uji',
            'investigator_institution' => 'Instansi Uji',
            'investigator_phone' => '081234567890',
            'submission_token' => $token,
        ];

        $first = $service->createExternal($data, UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'), $user);
        $second = $service->createExternal($data, UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'), $user);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('expert_witness_requests', 1);
        $this->assertCount(1, $first->documents()->get());
    }

    public function test_farmapol_reference_uses_only_the_linked_request(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'has_expert_witness_request' => true,
        ]);
        $otherRequest = TestRequest::factory()->create(['user_id' => $user->id]);

        $sample = Sample::factory()->create(['test_request_id' => $testRequest->id, 'sample_code' => 'SAMP-001']);
        $sample->testProcesses()->create([
            'stage' => 'interpretation',
            'completed_at' => now(),
            'metadata' => ['lhu_number' => 'LHU-001'],
        ]);
        Document::factory()->generated()->create([
            'investigator_id' => $testRequest->investigator_id,
            'test_request_id' => $testRequest->id,
            'sample_id' => $sample->id,
            'document_type' => 'laporan_hasil_uji',
            'filename' => 'LHU-001.pdf',
            'original_filename' => 'LHU-001.pdf',
            'file_path' => 'generated/LHU-001.pdf',
            'path' => 'generated/LHU-001.pdf',
        ]);
        TestResult::create([
            'sample_id' => $sample->id,
            'tested_by' => $user->id,
            'test_method' => 'Metode uji',
            'equipment_used' => 'Instrumen uji',
            'active_substances' => [],
            'test_conclusion' => 'Positif mengandung zat uji',
            'result_status' => 'positive',
            'qc_approved' => true,
        ]);
        Sample::factory()->create(['test_request_id' => $otherRequest->id, 'sample_code' => 'SAMP-OTHER']);

        $sahli = ExpertWitnessRequest::create([
            'source' => ExpertWitnessRequest::SOURCE_FARMAPOL,
            'test_request_id' => $testRequest->id,
            'submitted_by' => $user->id,
            'letter_number' => 'B/1',
            'letter_date' => '2026-09-07',
            'investigator_name' => 'Penyidik',
            'investigator_institution' => 'Instansi',
            'investigator_phone' => '0800',
            'submitted_at' => now(),
        ]);

        $references = app(ExpertWitnessService::class)->farmapolReferences($sahli);

        $this->assertArrayHasKey('LHU-001', $references);
        $this->assertSame('SAMP-001', $references['LHU-001'][0]['sample_code']);
        $this->assertArrayNotHasKey('LHU-OTHER', $references);
    }

    public function test_milestone_completion_marks_request_complete_only_after_all_five_steps(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $request = ExpertWitnessRequest::factory()->create(['submitted_by' => $user->id]);
        $service = app(ExpertWitnessService::class);
        $service->seedMilestones($request);
        $request->update(['sprin_number' => 'SPRIN-001', 'sprin_date' => now()->toDateString()]);

        foreach (array_keys(ExpertWitnessRequest::MILESTONES) as $code) {
            $service->updateMilestone($request, $code, true, $user);
        }

        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_later_milestone_cannot_be_completed_before_previous_steps(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $request = ExpertWitnessRequest::factory()->create(['submitted_by' => $user->id]);
        $service = app(ExpertWitnessService::class);
        $service->seedMilestones($request);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->updateMilestone($request, 'signed_by_both', true, $user);
    }

    public function test_sahli_routes_require_permission_and_signed_downloads_require_authorization(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $unauthorized = User::factory()->create(['role' => 'investigator']);
        $request = app(ExpertWitnessService::class)->createExternal([
            'letter_number' => 'B/AUTH/2026',
            'letter_date' => '2026-09-07',
            'investigator_name' => 'Penyidik Uji',
            'investigator_institution' => 'Instansi Uji',
            'investigator_phone' => '081234567890',
            'submission_token' => (string) Str::uuid(),
        ], UploadedFile::fake()->create('auth.pdf', 100, 'application/pdf'), $admin);
        $document = $request->documents()->first();

        $this->actingAs($unauthorized)->get(route('sahli.index'))->assertRedirect();
        $this->actingAs($admin)->get(route('sahli.index'))->assertOk();

        $this->actingAs($admin)->get(route('sahli.documents.download', $document))->assertForbidden();
        $signedUrl = URL::signedRoute('sahli.documents.download', ['document' => $document]);
        $this->actingAs($admin)->get($signedUrl)->assertOk();
        $this->actingAs($unauthorized)->get($signedUrl)->assertRedirect();
    }

    public function test_opening_sahli_index_syncs_flagged_farmapol_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $investigator = Investigator::factory()->create();
        TestRequest::factory()->create([
            'user_id' => $admin->id,
            'investigator_id' => $investigator->id,
            'has_expert_witness_request' => true,
        ]);

        $this->actingAs($admin)->get(route('sahli.index'))->assertOk();

        $this->assertDatabaseHas('expert_witness_requests', [
            'source' => ExpertWitnessRequest::SOURCE_FARMAPOL,
            'investigator_id' => $investigator->id,
        ]);
    }

    public function test_sahli_index_displays_suspect_name_for_farmapol_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $investigator = Investigator::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'user_id' => $admin->id,
            'investigator_id' => $investigator->id,
            'suspect_name' => 'Tersangka Tampilan Sahli',
            'has_expert_witness_request' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('sahli.index'))
            ->assertOk()
            ->assertSee('Tersangka Tampilan Sahli');
    }

    public function test_livewire_milestone_action_requires_edit_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = ExpertWitnessRequest::factory()->create(['submitted_by' => $admin->id]);
        app(ExpertWitnessService::class)->seedMilestones($request);
        $unauthorized = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($unauthorized);

        Livewire::test(Show::class, ['expertWitnessRequest' => $request])
            ->call('toggleMilestone', 'draft_received')
            ->assertForbidden();
    }

    public function test_detail_view_exposes_copy_feedback_fallback(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = ExpertWitnessRequest::factory()->create(['submitted_by' => $admin->id]);
        app(ExpertWitnessService::class)->seedMilestones($request);

        $this->actingAs($admin)
            ->get(route('sahli.show', $request))
            ->assertOk()
            ->assertSee('copyValue')
            ->assertSee('Pilih nilai lalu salin.');
    }
}
