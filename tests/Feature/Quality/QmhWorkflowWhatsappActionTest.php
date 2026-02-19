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

class QmhWorkflowWhatsappActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_reviewer_can_approve_review_stage_via_whatsapp_command(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin', 'phone' => '081200000111', 'is_active' => true]);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin', 'phone' => '081200000222', 'is_active' => true]);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin', 'phone' => '081200000333', 'is_active' => true]);

        $revision = $this->createDraftRevision($creator);
        $revision->forceFill([
            'status' => 'in_review',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => null,
        ])->save();

        $actionCode = 'REV-APPROVE-123';

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
            'action_token_hash' => hash('sha256', $actionCode),
            'action_expires_at' => now()->addMinutes(30),
            'context_json' => [
                'approver_id' => $approver->id,
            ],
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281200000222@s.whatsapp.net', [
            (string) $task->id,
            'approve',
            $actionCode,
        ]);

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

        $this->assertNotNull($task->fresh()->token_consumed_at);
    }

    public function test_command_is_denied_for_non_assignee_phone(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin', 'phone' => '081200001111', 'is_active' => true]);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin', 'phone' => '081200001222', 'is_active' => true]);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin', 'phone' => '081200001333', 'is_active' => true]);

        $revision = $this->createDraftRevision($creator);
        $revision->forceFill([
            'status' => 'in_review',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
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
            ],
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281200099999@s.whatsapp.net', [
            (string) $task->id,
            'approve',
            'ANY-CODE',
        ]);

        $this->assertStringContainsString('tidak berwenang', $response);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'in_review',
        ]);
    }

    public function test_approver_can_reject_approval_stage_via_whatsapp_command(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin', 'phone' => '081200002111', 'is_active' => true]);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin', 'phone' => '081200002333', 'is_active' => true]);

        $revision = $this->createDraftRevision($creator);
        $revision->forceFill([
            'status' => 'in_approval',
            'dibuat_oleh' => $creator->id,
            'disahkan_oleh' => $approver->id,
        ])->save();

        $actionCode = 'APP-REJECT-456';

        $task = StaffTask::query()->create([
            'title' => 'Approval QMH via WA',
            'assigned_to' => $approver->id,
            'assigned_by' => $creator->id,
            'status' => StaffTask::STATUS_PENDING,
            'priority' => StaffTask::PRIORITY_HIGH,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => $revision->id,
            'workflow_stage' => StaffTask::WORKFLOW_STAGE_APPROVAL,
            'action_token_hash' => hash('sha256', $actionCode),
            'action_expires_at' => now()->addMinutes(30),
            'context_json' => [],
        ]);

        $response = app(QmhWorkflowCommand::class)->execute('6281200002333@s.whatsapp.net', [
            (string) $task->id,
            'reject',
            $actionCode,
            'Perlu',
            'revisi',
            'dokumen',
        ]);

        $this->assertStringContainsString('dikembalikan ke draft', $response);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('qmh_workflow_events', [
            'revision_id' => $revision->id,
            'event_type' => 'reject',
            'actor_id' => $approver->id,
        ]);
    }

    private function createDraftRevision(User $creator): QmhDocumentRevision
    {
        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => $this->makeDocCode('QMH-WA'),
            'title' => 'Draft QMH WA Action',
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
