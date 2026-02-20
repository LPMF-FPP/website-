<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use App\Services\WhatsApp\Commands\QmhWorkflowCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QmhWorkflowCommandUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createQmhPermissions();
    }

    public function test_inbox_returns_ready_to_copy_commands_for_active_tasks(): void
    {
        $assignee = User::factory()->create([
            'role' => 'admin',
            'phone' => '081211112222',
            'is_active' => true,
        ]);

        $assigner = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $task = StaffTask::query()->create([
            'title' => 'QMH Review Diperlukan',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'notify_whatsapp' => true,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => 77,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_REVIEW,
            'action_token_hash' => hash('sha256', 'OLDTOKEN'),
            'context_json' => ['doc_code' => 'QMH-SOP-77'],
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281211112222@s.whatsapp.net', ['inbox']);

        $this->assertStringContainsString('Inbox Task QMH', $response);
        $this->assertStringContainsString('/qmh '.$task->id.' approve', $response);
        $this->assertStringContainsString('/qmh '.$task->id.' reject', $response);

        $task->refresh();
        $this->assertNotNull($task->action_expires_at);
        $this->assertNull($task->token_consumed_at);
        $this->assertNotSame(hash('sha256', 'OLDTOKEN'), (string) $task->action_token_hash);
    }

    public function test_shortcut_approve_works_when_only_one_active_task_exists(): void
    {
        $creator = User::factory()->create(['role' => 'admin', 'phone' => '081200000111', 'is_active' => true]);
        $reviewer = User::factory()->create(['role' => 'admin', 'phone' => '081200000222', 'is_active' => true]);
        $approver = User::factory()->create(['role' => 'admin', 'phone' => '081200000333', 'is_active' => true]);

        $revision = $this->createDraftRevision($creator);
        $revision->forceFill([
            'status' => 'in_review',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => null,
        ])->save();

        $task = StaffTask::query()->create([
            'title' => 'Review QMH via WA',
            'assigned_to' => $reviewer->id,
            'assigned_by' => $creator->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => $revision->id,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_REVIEW,
            'action_token_hash' => hash('sha256', 'ANY-CODE'),
            'action_expires_at' => now()->addMinutes(30),
            'context_json' => [
                'approver_id' => $approver->id,
                'doc_code' => $revision->document?->doc_code,
            ],
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281200000222@s.whatsapp.net', ['approve']);

        $this->assertStringContainsString('diteruskan ke tahap approval', $response);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'in_approval',
            'disahkan_oleh' => $approver->id,
        ]);

        $this->assertDatabaseHas('staff_tasks', [
            'id' => $task->id,
            'status' => StaffTask::STATUS_COMPLETED,
        ]);
    }

    public function test_shortcut_approve_requests_inbox_when_multiple_tasks_exist(): void
    {
        $assignee = User::factory()->create([
            'role' => 'admin',
            'phone' => '081299998888',
            'is_active' => true,
        ]);

        $assigner = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        StaffTask::query()->create([
            'title' => 'Task A',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => 10,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_REVIEW,
        ]);

        StaffTask::query()->create([
            'title' => 'Task B',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => 11,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_APPROVAL,
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281299998888@s.whatsapp.net', ['approve']);

        $this->assertStringContainsString('lebih dari satu task aktif', $response);
        $this->assertStringContainsString('/qmh inbox', $response);
    }

    private function createDraftRevision(User $creator): QmhDocumentRevision
    {
        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => $this->makeDocCode('QMH-UX'),
            'title' => 'Draft QMH UX Command',
            'clause' => 8,
            'doc_type' => 'sop',
        ], $creator->id);

        return $document->currentRevision;
    }

    private function makeDocCode(string $prefix): string
    {
        return $prefix.'-'.(string) Str::uuid();
    }

    private function createQmhPermissions(): void
    {
        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);
    }
}
