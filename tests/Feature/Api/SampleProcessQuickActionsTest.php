<?php

namespace Tests\Feature\Api;

use App\Enums\SampleStatus;
use App\Models\AuditTrail;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleProcessQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_creates_next_stage_process_and_logs_audit(): void
    {
        $user = User::factory()->createOne([
            'role' => 'analis',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        assert($user instanceof User);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_testing',
            'submitted_at' => now(),
            'verified_at' => now(),
            'received_at' => now(),
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => SampleStatus::PREPARATION_IN_PROGRESS->value,
            'sample_status' => 'in_testing',
        ]);

        $prep = SampleTestProcess::factory()->preparation()->create([
            'sample_id' => $sample->id,
            'performed_by' => $user->id,
            'started_at' => now()->subHour(),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/processes/{$prep->id}/complete");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.next_stage', 'instrumentation');

        $nextId = $response->json('data.next_process_id');
        $this->assertNotNull($nextId);

        $this->assertDatabaseHas('sample_test_processes', [
            'id' => $nextId,
            'sample_id' => $sample->id,
            'stage' => 'instrumentation',
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'table_name' => 'sample_test_processes',
            'record_id' => (string) $prep->id,
            'action' => 'process_completed',
            'changed_by' => (string) $user->id,
        ]);
    }

    public function test_unlock_is_blocked_when_next_stage_has_progress(): void
    {
        $user = User::factory()->createOne([
            'role' => 'analis',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        assert($user instanceof User);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_testing',
            'submitted_at' => now(),
            'verified_at' => now(),
            'received_at' => now(),
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS->value,
            'sample_status' => 'in_testing',
        ]);

        $inst = SampleTestProcess::factory()->instrumentation()->completed()->create([
            'sample_id' => $sample->id,
            'performed_by' => $user->id,
            'started_at' => now()->subHours(3),
        ]);

        SampleTestProcess::factory()->interpretation()->create([
            'sample_id' => $sample->id,
            'performed_by' => $user->id,
            'started_at' => now()->subHour(),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/processes/{$inst->id}/unlock", [
                'reason' => 'Perlu koreksi data instrumentasi yang salah input',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);

        $inst->refresh();
        $this->assertNotNull($inst->completed_at);

        $this->assertDatabaseMissing('audit_trails', [
            'table_name' => 'sample_test_processes',
            'record_id' => (string) $inst->id,
            'action' => 'process_unlocked',
        ]);
    }

    public function test_unlock_requires_reason_and_logs_audit(): void
    {
        $user = User::factory()->createOne([
            'role' => 'analis',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        assert($user instanceof User);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_testing',
            'submitted_at' => now(),
            'verified_at' => now(),
            'received_at' => now(),
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => SampleStatus::INTERPRETATION_DONE->value,
            'sample_status' => 'tested',
        ]);

        $process = SampleTestProcess::factory()->interpretation()->completed()->create([
            'sample_id' => $sample->id,
            'performed_by' => $user->id,
            'started_at' => now()->subHours(2),
        ]);

        $invalid = $this->actingAs($user)
            ->postJson("/api/processes/{$process->id}/unlock", [
                'reason' => 'kurang',
            ]);

        $invalid->assertStatus(422);

        $validReason = 'Perbaikan interpretasi untuk sinkronisasi hasil validasi.';

        $valid = $this->actingAs($user)
            ->postJson("/api/processes/{$process->id}/unlock", [
                'reason' => $validReason,
            ]);

        $valid->assertOk()
            ->assertJsonPath('ok', true);

        $process->refresh();
        $this->assertNull($process->completed_at);

        $this->assertDatabaseHas('audit_trails', [
            'table_name' => 'sample_test_processes',
            'record_id' => (string) $process->id,
            'action' => 'process_unlocked',
            'changed_by' => (string) $user->id,
            'change_reason' => $validReason,
        ]);

        $trail = AuditTrail::query()
            ->where('table_name', 'sample_test_processes')
            ->where('record_id', (string) $process->id)
            ->where('action', 'process_unlocked')
            ->latest('changed_at')
            ->first();

        $this->assertNotNull($trail);
    }

    public function test_show_endpoint_returns_guided_action_flags(): void
    {
        $user = User::factory()->createOne([
            'role' => 'analis',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        assert($user instanceof User);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_testing',
            'submitted_at' => now(),
            'verified_at' => now(),
            'received_at' => now(),
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => SampleStatus::PREPARATION_PENDING->value,
            'sample_status' => 'in_testing',
        ]);

        $process = SampleTestProcess::factory()->instrumentation()->create([
            'sample_id' => $sample->id,
            'performed_by' => $user->id,
            'started_at' => null,
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/processes/{$process->id}");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $process->id)
            ->assertJsonPath('data.can_start', false)
            ->assertJsonPath('data.can_complete', false)
            ->assertJsonPath('data.can_unlock', false);

        $this->assertNotEmpty($response->json('data.start_reason'));
        $this->assertNotEmpty($response->json('data.complete_reason'));
    }
}
