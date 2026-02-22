<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhAudit;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhGovernanceWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createQmhPermissions();
    }

    public function test_admin_can_access_governance_module_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/rapat')
            ->assertOk()
            ->assertViewIs('quality.rapat.index');

        $this->actingAs($user)
            ->get('/quality/audit')
            ->assertOk()
            ->assertViewIs('quality.audit.index');

        $this->actingAs($user)
            ->get('/quality/kum')
            ->assertOk()
            ->assertViewIs('quality.kum.index');

        $this->actingAs($user)
            ->get('/quality/governance')
            ->assertOk()
            ->assertViewIs('quality.governance.index');
    }

    public function test_user_without_qmh_permission_cannot_access_governance_modules(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/rapat')
            ->assertRedirect('/dashboard');

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/audit')
            ->assertRedirect('/dashboard');

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/kum')
            ->assertRedirect('/dashboard');

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/governance')
            ->assertRedirect('/dashboard');
    }

    public function test_can_create_rapat_and_manage_action_item_workflow(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        /** @var User $assignee */
        $assignee = User::factory()->create(['role' => 'analis']);

        $response = $this->actingAs($user)
            ->post('/quality/rapat', [
                'title' => 'Rapat Evaluasi Mingguan',
                'meeting_type' => 'mingguan',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'location' => 'Ruang Mutu',
                'agenda' => 'Evaluasi KPI mutu laboratorium',
                'status' => 'scheduled',
                'participants' => [$assignee->id],
            ]);

        $response->assertRedirect();

        $rapat = QmhRapat::query()->firstOrFail();

        $this->assertDatabaseHas('qmh_rapats', [
            'id' => $rapat->id,
            'title' => 'Rapat Evaluasi Mingguan',
            'meeting_type' => 'mingguan',
        ]);

        $this->actingAs($user)
            ->post('/quality/rapat/'.$rapat->id.'/action-items', [
                'title' => 'Tindak lanjut CAPA',
                'description' => 'Selesaikan tindak lanjut temuan mayor',
                'assignee_id' => $assignee->id,
                'due_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect();

        $actionItem = QmhRapatActionItem::query()->firstOrFail();

        $this->assertDatabaseHas('qmh_rapat_action_items', [
            'id' => $actionItem->id,
            'status' => QmhRapatActionItem::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qmh_rapat_action_items', [
            'id' => $actionItem->id,
            'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_OVERDUE,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('qmh_rapat_action_items', [
            'id' => $actionItem->id,
            'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_CLOSED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('qmh_rapat_action_items', [
            'id' => $actionItem->id,
            'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_can_create_audit_and_kum_records(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        /** @var User $auditor */
        $auditor = User::factory()->create(['role' => 'analis']);

        $this->actingAs($user)
            ->post('/quality/audit', [
                'title' => 'Audit Internal Q1',
                'audit_type' => 'internal',
                'scope' => 'Gudang dan dokumentasi',
                'status' => 'draft',
                'auditors_json' => [$auditor->id],
            ])
            ->assertRedirect();

        $audit = QmhAudit::query()->firstOrFail();

        $this->assertDatabaseHas('qmh_audits', [
            'title' => 'Audit Internal Q1',
            'audit_type' => 'internal',
        ]);

        $this->assertDatabaseHas('qmh_audit_auditors', [
            'audit_id' => $audit->id,
            'user_id' => $auditor->id,
        ]);

        $this->actingAs($auditor)
            ->get('/quality/audit/'.$audit->id)
            ->assertOk();

        /** @var User $outsider */
        $outsider = User::factory()->create(['role' => 'analis']);
        $this->actingAs($outsider)
            ->get('/quality/audit/'.$audit->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/quality/kum', [
                'title' => 'KUM Tahunan',
                'year' => 2026,
                'period' => 'annual',
                'status' => 'draft',
                'participants_json_text' => "Kepala Lab\nManajer Mutu",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qmh_kums', [
            'title' => 'KUM Tahunan',
            'year' => 2026,
            'period' => 'annual',
        ]);
    }

    public function test_can_send_rapat_pdf_to_whatsapp_individual_and_log_batch(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Tinjauan Bulanan',
            'meeting_type' => 'bulanan',
            'scheduled_at' => now()->addDay(),
            'location' => 'Ruang Utama',
            'agenda' => 'Tinjauan mutu bulanan',
            'status' => 'scheduled',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->mock(GowaClient::class, function ($mock): void {
            $mock->shouldReceive('sendFile')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message_id' => 'qmh-test-message-id',
                ]);
        });

        $this->actingAs($user)
            ->post('/quality/rapat/'.$rapat->id.'/whatsapp/send', [
                'recipient_type' => 'individual',
                'recipient_value' => '081234567890',
                'message' => 'Mohon ditinjau.',
            ])
            ->assertRedirect('/quality/rapat/'.$rapat->id)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_message_batches', [
            'type' => 'qmh_rapat_summary',
            'source_type' => QmhRapat::class,
            'source_id' => $rapat->id,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'recipient_jid' => '6281234567890@s.whatsapp.net',
            'recipient_type' => 'individual',
            'status' => 'sent',
        ]);
    }

    public function test_can_upload_multiple_rapat_attachments(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Dokumentasi',
            'meeting_type' => 'ad_hoc',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/quality/rapat/'.$rapat->id.'/attachments', [
                'files' => [
                    UploadedFile::fake()->image('foto-rapat-1.jpg'),
                    UploadedFile::fake()->create('ringkasan-rapat.pdf', 256, 'application/pdf'),
                ],
                'notes' => 'Dokumentasi rapat mingguan',
            ])
            ->assertRedirect('/quality/rapat/'.$rapat->id)
            ->assertSessionHas('success');

        $this->assertDatabaseCount('qmh_rapat_attachments', 2);

        $this->assertDatabaseHas('qmh_rapat_attachments', [
            'rapat_id' => $rapat->id,
            'notes' => 'Dokumentasi rapat mingguan',
        ]);

        $paths = \App\Models\QmhRapatAttachment::query()->pluck('file_path')->all();
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    private function createQmhPermissions(): void
    {
        $permissions = [
            'qmh.view',
            'qmh.create',
            'qmh.rapat.view',
            'qmh.rapat.view.all',
            'qmh.rapat.create',
            'qmh.rapat.create.all',
            'qmh.rapat.edit',
            'qmh.rapat.delete',
            'qmh.audit.view',
            'qmh.audit.view.all',
            'qmh.audit.create',
            'qmh.audit.create.all',
            'qmh.audit.edit',
            'qmh.audit.delete',
            'qmh.kum.view',
            'qmh.kum.view.all',
            'qmh.kum.create',
            'qmh.kum.create.all',
            'qmh.kum.edit',
            'qmh.kum.delete',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => strtoupper($permissionName),
                    'module' => 'qmh',
                    'action' => 'manage',
                ]
            );

            RolePermission::query()->updateOrCreate([
                'role' => 'admin',
                'permission_id' => $permission->id,
            ]);
        }
    }
}
