<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhAudit;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->get('/quality/rapat')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/quality/audit')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/quality/kum')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/quality/governance')
            ->assertForbidden();
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
