<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\SampleDisposalStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SampleDisposalEligibilityService;
use App\Services\SampleDisposalService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SampleDisposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 12:00:00'));
        $this->seed(PermissionSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'inventory.disposal_retention_days'], ['value' => 90]);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_independent_permissions_are_not_granted_by_inventory_roles_or_exposed_on_dashboard(): void
    {
        $actor = User::factory()->create();
        $actor->grantPermission('inventori.view');
        $actor->grantPermission('inventori.edit');
        $sample = $this->eligible();
        $record = SampleDisposal::factory()->create();
        $ids = Permission::whereIn('name', ['sample-disposal.view', 'sample-disposal.execute'])->pluck('id');
        $this->assertSame(2, $ids->count());
        $this->assertSame(0, RolePermission::whereIn('permission_id', $ids)->count());
        foreach (['inventory.disposal.index' => [], 'inventory.disposal.create' => ['sample_ids' => [$sample->id]], 'inventory.disposal.show' => ['disposal' => $record->id], 'inventory.disposal.pdf' => ['disposal' => $record->id]] as $route => $params) {
            $this->actingAs($actor)->getJson(route($route, $params))->assertForbidden();
        }
        $this->postJson(route('inventory.disposal.store'), [])->assertForbidden();
        $this->get(route('inventory.dashboard'))->assertOk()->assertDontSee('Pemusnahan Sampel')
            ->assertViewHas('eligibleSamplesCount', null)->assertViewHas('recentSampleDisposals', fn ($records) => $records->isEmpty());
    }

    public function test_sample_only_viewer_can_read_but_cannot_preview_or_execute(): void
    {
        $viewer = $this->operator(false);
        $sample = $this->eligible();
        $this->actingAs($viewer)->get(route('inventory.disposal.index'))->assertOk()->assertSee($sample->sample_code);
        $this->getJson(route('inventory.disposal.create', ['sample_ids' => [$sample->id]]))->assertForbidden();
        $this->postJson(route('inventory.disposal.store'), [])->assertForbidden();
    }

    public function test_permission_panel_exposes_separate_sample_view_and_execution_actions(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'analis']);
        $this->actingAs($manager)->get(route('analysts.show', $target))->assertOk()
            ->assertSee('Pemusnahan Sampel')
            ->assertSee('aria-label="Pemusnahan Sampel: Eksekusi Pemusnahan Sampel"', false);
    }

    public function test_qualifying_anchor_age_month_and_counts_ignore_newer_nonqualifying_processes(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible(100);
        $later = $sample->testProcesses()->firstOrFail();
        $later->update(['completed_at' => now()->subDays(91), 'metadata' => ['lhu_number' => 'CANONICAL-LHU']]);
        foreach ([['stage' => 'preparation', 'completed_at' => now()->subDay(), 'metadata' => ['lhu_number' => 'TOO-RECENT']], ['stage' => 'instrumentation', 'completed_at' => now()->subDays(95), 'metadata' => []], ['stage' => 'administration', 'completed_at' => now()->addDay(), 'metadata' => ['lhu_number' => 'FUTURE']]] as $process) {
            SampleTestProcess::factory()->create(['sample_id' => $sample->id, ...$process]);
        }
        $service = app(SampleDisposalEligibilityService::class);
        $row = $service->projection($service->query()->findOrFail($sample->id));
        $this->assertSame($later->id, $row['anchor_id']);
        $this->assertSame(91, $row['age_days']);
        $month = $later->completed_at->format('Y-m');
        $this->assertSame(1, $service->monthlySummaries()->firstWhere('key', $month)['count']);
        $preview = app(SampleDisposalService::class)->preview($actor, [$sample->id]);
        $this->assertSame($row, $preview['manifest']['samples'][0]);
        $this->actingAs($actor)->get(route('inventory.disposal.index'))->assertOk()->assertSee('91 hari')->assertDontSee('-91');
        $this->get(route('inventory.disposal.create', ['month' => $month]))->assertOk()->assertSee('CANONICAL-LHU')->assertDontSee('TOO-RECENT');
        $actor->grantPermission('inventori.view');
        $this->get(route('inventory.dashboard'))->assertOk()->assertViewHas('eligibleSamplesCount', 1);
    }

    public function test_retention_boundary_missing_lhu_future_and_disposed_samples_use_one_predicate(): void
    {
        $atBoundary = $this->eligible(90);
        $recent = $this->eligible(89);
        $future = $this->eligible(-1);
        $missing = $this->eligible(91);
        $missing->testProcesses()->update(['metadata' => []]);
        $disposed = $this->eligible(91);
        $disposed->update(['disposal_status' => SampleDisposalStatus::DISPOSED, 'disposed_at' => now()]);
        $this->assertSame([$atBoundary->id], Sample::eligibleForDisposal()->pluck('id')->all());
        $this->assertTrue($atBoundary->isEligibleForDisposal());
        foreach ([$recent, $future, $missing, $disposed] as $sample) {
            $this->assertFalse($sample->isEligibleForDisposal());
        }
    }

    public function test_blank_approver_missing_review_and_conflicting_selection_are_denied(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $this->actingAs($actor)->postJson(route('inventory.disposal.store'), [...$data, 'approver_name' => ' ', 'approver_role' => ' '])->assertUnprocessable()->assertJsonValidationErrors(['approver_name', 'approver_role']);
        $this->postJson(route('inventory.disposal.store'), [...$data, 'review_token' => 'not-authentic'])->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->postJson(route('inventory.disposal.store'), [...$data, 'review_confirmed' => false])->assertUnprocessable();
        $other = $this->eligible();
        $this->postJson(route('inventory.disposal.store'), [...$data, 'sample_ids' => [$other->id]])->assertUnprocessable()->assertJsonValidationErrors('sample_ids');
        $this->assertSame(0, SampleDisposal::count());
    }

    public function test_html_validation_redirect_keeps_evidence_and_exposes_approver_error(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $url = route('inventory.disposal.create', ['sample_ids' => [$sample->id]]);
        $this->actingAs($actor)->from($url)->post(route('inventory.disposal.store'), [...$data, 'approver_name' => ''])
            ->assertRedirect($url)->assertSessionHasErrors('approver_name');
        $this->get($url)->assertOk()->assertSee('Nama approver wajib diisi.')
            ->assertViewHas('witnessRows', fn ($rows) => $rows[0]['name'] === 'Saksi Verifikasi');
    }

    public function test_native_manifest_ids_use_one_json_field_and_still_require_exact_reviewed_selection(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $ids = $data['sample_ids'];
        unset($data['sample_ids']);
        $preview = $this->actingAs($actor)->get(route('inventory.disposal.create', ['sample_ids' => $ids]));
        $preview->assertOk()->assertSee('name="sample_ids_json"', false)->assertDontSee('name="sample_ids[]"', false);
        $data['sample_ids_json'] = json_encode($ids);
        $this->postJson(route('inventory.disposal.store'), $data)->assertRedirect();
        $this->assertNotNull($sample->fresh()->disposal_id);
    }

    public function test_malformed_witness_input_recovers_with_renderable_rows(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $url = route('inventory.disposal.create', ['sample_ids' => [$sample->id]]);
        foreach (['invalid', ['named' => ['name' => 'Invalid shape']], ['invalid row']] as $rows) {
            $this->actingAs($actor)->from($url)->post(route('inventory.disposal.store'), [...$data, 'witnesses' => $rows])
                ->assertRedirect($url)->assertSessionHasErrors();
            $this->get($url)->assertOk()->assertViewHas('witnessRows', fn ($value) => array_is_list($value) && is_array($value[0]));
        }
        $this->assertSame(0, SampleDisposal::count());
    }

    public function test_review_shows_recorded_case_fields_and_pdf_never_fills_missing_signers_from_current_profile(): void
    {
        $actor = $this->operator();
        $actor->update(['rank' => null, 'nrp' => null, 'nip' => null]);
        $sample = $this->eligible();
        $sample->testRequest->update(['case_number' => 'REVIEW-CASE-123']);
        $this->actingAs($actor)->get(route('inventory.disposal.create', ['sample_ids' => [$sample->id]]))
            ->assertOk()->assertSee('REVIEW-CASE-123')->assertSee('Tanggal tidak tercatat');
        $record = app(SampleDisposalService::class)->execute($actor, $this->execution($actor, [$sample]));
        $actor->update(['rank' => 'CHANGED-RANK', 'nrp' => 'CHANGED-NRP']);
        $sample->testRequest->update(['case_number' => 'CHANGED-CASE']);
        $this->get(route('inventory.disposal.show', $record))->assertOk()->assertSee('REVIEW-CASE-123')->assertDontSee('CHANGED-CASE');
        $html = view('pdf.berita-acara-pemusnahan', ['disposal' => $record->fresh()])->render();
        $this->assertStringNotContainsString('CHANGED-RANK', $html);
        $this->assertStringNotContainsString('CHANGED-NRP', $html);
        $this->assertStringContainsString('Asia/Jakarta', $html);
    }

    public function test_stale_process_or_retention_context_rejects_whole_batch_without_partial_effects(): void
    {
        $actor = $this->operator();
        $first = $this->eligible();
        $second = $this->eligible();
        $data = $this->execution($actor, [$first, $second]);
        $second->testProcesses()->update(['metadata' => ['lhu_number' => 'REVISED-LHU']]);
        $this->actingAs($actor)->postJson(route('inventory.disposal.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('sample_ids');
        $this->assertNull($first->fresh()->disposal_id);
        $this->assertSame(0, SampleDisposal::count());
        $data = $this->execution($actor, [$first]);
        SystemSetting::where('key', 'inventory.disposal_retention_days')->firstOrFail()->update(['value' => 180]);
        $this->postJson(route('inventory.disposal.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('sample_ids');
        $this->assertNull($first->fresh()->disposal_id);
    }

    public function test_identical_retry_returns_same_batch_and_changed_evidence_is_rejected(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $item = \App\Models\InventoryItem::factory()->create();
        $location = \App\Models\InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMovement::factory()->create(['item_id' => $item->id, 'movement_type' => 'RECEIPT', 'to_location_id' => $location->id, 'qty' => 10]);
        InventoryBalance::create(['item_id' => $item->id, 'location_id' => $location->id, 'on_hand_qty' => 10]);
        $ledgerCount = InventoryMovement::count();
        $balances = InventoryBalance::all()->toArray();
        $service = app(SampleDisposalService::class);
        $first = $service->execute($actor, $data);
        $second = $service->execute($actor, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SampleDisposal::count());
        $this->assertSame($first->id, $sample->fresh()->disposal_id);
        $this->assertSame($ledgerCount, InventoryMovement::count());
        $this->assertSame($balances, InventoryBalance::all()->toArray());
        try {
            $service->execute($actor, [...$data, 'method' => 'kubur']);
            $this->fail('Conflicting replay should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('review_token', $exception->errors());
        }
    }

    public function test_stored_manifest_and_pdf_do_not_change_with_live_sample_process_and_identity(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $originalCode = $sample->sample_code;
        $disposal = app(SampleDisposalService::class)->execute($actor, $this->execution($actor, [$sample]));
        $sample->update(['sample_code' => 'CHANGED-CODE', 'short_description' => 'Changed description']);
        $sample->testProcesses()->update(['metadata' => ['lhu_number' => 'CHANGED-LHU']]);
        $this->assertSame($originalCode, $disposal->fresh()->manifest_rows[0]['sample_code']);
        $this->actingAs($actor)->get(route('inventory.disposal.show', $disposal))->assertOk()->assertSee($originalCode)->assertDontSee('CHANGED-CODE')->assertDontSee('CHANGED-LHU');
        $html = view('pdf.berita-acara-pemusnahan', ['disposal' => $disposal->fresh()])->render();
        $this->assertStringContainsString($originalCode, $html);
        $this->assertStringNotContainsString('CHANGED-LHU', $html);
        $this->get(route('inventory.disposal.pdf', $disposal))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_later_sample_failure_rolls_back_batch_samples_and_staged_private_photos(): void
    {
        $actor = $this->operator();
        $first = $this->eligible();
        $second = $this->eligible();
        $data = $this->execution($actor, [$first, $second]);
        $data['documentation_photos'] = [UploadedFile::fake()->image('proof.jpg')];
        $dispatcher = Sample::getEventDispatcher();
        Sample::setEventDispatcher(clone $dispatcher);
        try {
            Sample::updating(function ($sample) use ($second): void {
                if ($sample->id === $second->id && $sample->isDirty('disposal_id')) {
                    throw new \RuntimeException('Injected later sample failure');
                }
            });
            try {
                app(SampleDisposalService::class)->execute($actor, $data);
                $this->fail('Injected failure must abort the batch.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Injected later sample failure', $exception->getMessage());
            }
        } finally {
            Sample::setEventDispatcher($dispatcher);
        }
        $this->assertSame(0, SampleDisposal::count());
        $this->assertNull($first->fresh()->disposal_id);
        $this->assertNull($second->fresh()->disposal_id);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_partial_upload_failure_removes_staged_file_and_writes_no_batch(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $data['documentation_photos'] = [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')];
        $disk = Storage::disk('local');
        $path = 'disposal-documentation/staged-first.jpg';
        $disk->put($path, 'staged-test-file');
        $proxy = \Mockery::mock($disk);
        $calls = 0;
        $proxy->shouldReceive('putFileAs')->twice()->andReturnUsing(function () use ($path, &$calls) {
            if ($calls++ === 0) {
                return $path;
            }
            throw new \RuntimeException('Injected upload failure');
        });
        $proxy->shouldReceive('delete')->with($path)->once()->andReturnUsing(fn () => $disk->delete($path));
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        try {
            app(SampleDisposalService::class)->execute($actor, $data);
            $this->fail('Upload failure must abort execution.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected upload failure', $exception->getMessage());
        }
        $this->assertSame([], $disk->allFiles());
        $this->assertSame(0, SampleDisposal::count());
        $this->assertNull($sample->fresh()->disposal_id);
    }

    public function test_audit_record_failure_leaves_samples_and_private_storage_unchanged(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $data['documentation_photos'] = [UploadedFile::fake()->image('audit-proof.png')];
        $this->assertSame(0, SampleDisposal::count());
        $dispatcher = SampleDisposal::getEventDispatcher();
        SampleDisposal::setEventDispatcher(clone $dispatcher);
        try {
            SampleDisposal::creating(fn () => throw new \RuntimeException('Injected audit record failure'));
            try {
                app(SampleDisposalService::class)->execute($actor, $data);
                $this->fail('Audit creation failure must abort execution.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Injected audit record failure', $exception->getMessage());
            }
        } finally {
            SampleDisposal::setEventDispatcher($dispatcher);
        }
        $this->assertSame(0, SampleDisposal::count());
        $this->assertNull($sample->fresh()->disposal_id);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_database_read_failure_is_not_presented_as_an_empty_eligible_list(): void
    {
        $actor = $this->operator(false);
        $this->mock(SampleDisposalEligibilityService::class, function ($mock): void {
            $mock->shouldReceive('query')->andThrow(new \Illuminate\Database\QueryException('pgsql', 'read eligibility', [], new \PDOException('verification failure')));
        });
        $this->actingAs($actor)->get(route('inventory.disposal.index'))->assertStatus(503)
            ->assertSee('Data pemusnahan gagal dimuat')->assertSee('Coba lagi')->assertDontSee('Belum ada sampel yang memenuhi syarat pemusnahan');
    }

    public function test_number_collision_retry_uses_savepoint_and_preserves_atomic_sample_update(): void
    {
        $actor = $this->operator();
        $existing = SampleDisposal::factory()->create();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $dispatcher = SampleDisposal::getEventDispatcher();
        SampleDisposal::setEventDispatcher(clone $dispatcher);
        $attempts = 0;
        try {
            SampleDisposal::creating(function ($record) use ($existing, &$attempts): void {
                if ($attempts++ === 0) {
                    $record->batch_number = $existing->batch_number;
                }
            });
            $record = app(SampleDisposalService::class)->execute($actor, $data);
        } finally {
            SampleDisposal::setEventDispatcher($dispatcher);
        }
        $this->assertSame(2, $attempts);
        $this->assertNotSame($existing->batch_number, $record->batch_number);
        $this->assertSame($record->id, $sample->fresh()->disposal_id);
    }

    public function test_private_documentation_requires_sample_permission_and_known_batch_attachment(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $data = $this->execution($actor, [$sample]);
        $data['documentation_photos'] = [UploadedFile::fake()->image('proof.png')];
        $disposal = app(SampleDisposalService::class)->execute($actor, $data);
        $photo = $disposal->documentation_photos[0];
        $this->assertSame('local', $photo['disk']);
        Storage::disk('local')->assertExists($photo['path']);
        $url = route('inventory.disposal.documentation.file', ['disposal' => $disposal, 'photo' => rawurlencode($photo['path'])]);
        $this->actingAs($actor)->get($url)->assertOk();
        $this->get(route('inventory.disposal.documentation.file', ['disposal' => $disposal, 'photo' => rawurlencode('../unknown.png')]))->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    public function test_persisted_permission_revocation_denies_execution_even_with_cached_access(): void
    {
        $actor = $this->operator();
        $data = $this->execution($actor, [$this->eligible()]);
        $this->assertTrue($actor->hasPermission('sample-disposal.execute'));
        DB::table('user_permissions')->where('user_id', $actor->id)->where('permission_id', Permission::where('name', 'sample-disposal.execute')->value('id'))->update(['granted' => false]);
        $this->actingAs($actor)->postJson(route('inventory.disposal.store'), $data)->assertForbidden();
        $this->assertSame(0, SampleDisposal::count());
    }

    public function test_completed_batch_audit_evidence_cannot_be_rewritten_and_recorded_timezone_is_stable(): void
    {
        $actor = $this->operator();
        $record = app(SampleDisposalService::class)->execute($actor, $this->execution($actor, [$this->eligible()]));
        $timestamp = $record->recorded_executed_at->toIso8601String();
        config(['app.timezone' => 'UTC']);
        $this->assertSame($timestamp, $record->fresh()->recorded_executed_at->toIso8601String());
        try {
            $record->update(['approver_name' => 'Changed approver']);
            $this->fail('Completed evidence must not be rewritten.');
        } catch (\RuntimeException) {
            $this->assertSame('Approver Verifikasi', $record->fresh()->approver_name);
        }
    }

    private function operator(bool $execute = true): User
    {
        $user = User::factory()->create(['rank' => 'Pengawas']);
        $user->grantPermission('sample-disposal.view');
        if ($execute) {
            $user->grantPermission('sample-disposal.execute');
        }

        return $user;
    }

    private function eligible(int $days = 91): Sample
    {
        $sample = Sample::factory()->create(['disposal_id' => null, 'disposed_at' => null, 'disposal_status' => SampleDisposalStatus::PENDING]);
        SampleTestProcess::factory()->create(['sample_id' => $sample->id, 'stage' => 'interpretation', 'completed_at' => now()->subDays($days), 'metadata' => ['lhu_number' => 'LHU-'.$sample->id]]);

        return $sample;
    }

    private function execution(User $actor, array $samples): array
    {
        $ids = array_map(fn ($sample) => $sample->id, $samples);
        $preview = app(SampleDisposalService::class)->preview($actor, $ids);

        return ['sample_ids' => $ids, 'review_token' => $preview['review_token'], 'review_confirmed' => '1', 'method' => 'bakar',
            'witnesses' => [['user_id' => null, 'name' => 'Saksi Verifikasi', 'role' => 'Pengawas']],
            'approver_name' => 'Approver Verifikasi', 'approver_role' => 'Kepala Lab'];
    }
}
