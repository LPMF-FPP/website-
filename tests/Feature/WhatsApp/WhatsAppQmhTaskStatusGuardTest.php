<?php

namespace Tests\Feature\WhatsApp;

use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppQmhTaskStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_qmh_workflow_task_status_cannot_be_changed_from_whatsapp_hub(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $assigner */
        $assigner = User::factory()->create(['role' => 'admin']);
        /** @var User $assignee */
        $assignee = User::factory()->create(['role' => 'manajer_teknis']);

        $task = StaffTask::query()->create([
            'title' => 'QMH Review Diperlukan',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'notify_whatsapp' => true,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => 999,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_REVIEW,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('whatsapp.tasks.status', $task), [
                'status' => StaffTask::STATUS_COMPLETED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Task workflow QMH hanya dapat diproses melalui command WhatsApp /qmh atau modul QMH.');

        $this->assertDatabaseHas('staff_tasks', [
            'id' => $task->id,
            'status' => StaffTask::STATUS_PENDING,
        ]);
    }

    public function test_non_qmh_task_status_can_still_be_changed_from_whatsapp_hub(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $assigner */
        $assigner = User::factory()->create(['role' => 'admin']);
        /** @var User $assignee */
        $assignee = User::factory()->create(['role' => 'analis']);

        $task = StaffTask::query()->create([
            'title' => 'Task Non-QMH',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_NORMAL,
            'notify_whatsapp' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('whatsapp.tasks.status', $task), [
                'status' => StaffTask::STATUS_COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('task.status', StaffTask::STATUS_COMPLETED);

        $this->assertDatabaseHas('staff_tasks', [
            'id' => $task->id,
            'status' => StaffTask::STATUS_COMPLETED,
        ]);
    }
}
