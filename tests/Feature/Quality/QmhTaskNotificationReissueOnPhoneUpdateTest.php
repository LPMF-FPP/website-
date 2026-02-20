<?php

namespace Tests\Feature\Quality;

use App\Jobs\SendQmhWorkflowTaskNotificationJob;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QmhTaskNotificationReissueOnPhoneUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reissues_qmh_task_notification_when_assignee_phone_is_added(): void
    {
        Queue::fake();

        $assigner = User::factory()->create(['role' => 'admin']);
        $assignee = User::factory()->create(['role' => 'manajer_teknis', 'phone' => null]);

        $task = StaffTask::query()->create([
            'title' => 'QMH Review Diperlukan',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'notify_whatsapp' => true,
            'notification_sent' => false,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => 123,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_REVIEW,
            'action_token_hash' => hash('sha256', 'OLDTOKEN'),
            'action_expires_at' => now()->subMinutes(5),
            'token_consumed_at' => now()->subMinutes(5),
        ]);

        $oldHash = (string) $task->action_token_hash;

        $assignee->update(['phone' => '081234567890']);

        $task->refresh();

        $this->assertNotSame($oldHash, (string) $task->action_token_hash);
        $this->assertNotNull($task->action_expires_at);
        $this->assertNull($task->token_consumed_at);

        Queue::assertPushed(SendQmhWorkflowTaskNotificationJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id;
        });
    }

    public function test_it_does_not_reissue_for_non_qmh_tasks(): void
    {
        Queue::fake();

        $assigner = User::factory()->create(['role' => 'admin']);
        $assignee = User::factory()->create(['role' => 'manajer_teknis', 'phone' => null]);

        StaffTask::query()->create([
            'title' => 'Task biasa',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_NORMAL,
            'notify_whatsapp' => true,
            'notification_sent' => false,
        ]);

        $assignee->update(['phone' => '081234567891']);

        Queue::assertNotPushed(SendQmhWorkflowTaskNotificationJob::class);
    }
}
