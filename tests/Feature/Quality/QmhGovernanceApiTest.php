<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhAudit;
use App\Models\QmhKum;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_unauthenticated_user_cannot_access_governance_api_endpoints(): void
    {
        $this->getJson('/api/quality/rapat')->assertUnauthorized();
        $this->getJson('/api/quality/audit')->assertUnauthorized();
        $this->getJson('/api/quality/kum')->assertUnauthorized();
    }

    public function test_user_without_permission_gets_forbidden_on_governance_api_endpoints(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)->getJson('/api/quality/rapat')->assertForbidden();
        $this->actingAs($user)->getJson('/api/quality/audit')->assertForbidden();
        $this->actingAs($user)->getJson('/api/quality/kum')->assertForbidden();
    }

    public function test_user_with_audit_module_permission_can_access_audit_endpoint_without_legacy_qmh_view(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'analis']);

        $auditPermission = Permission::query()->firstOrCreate(
            ['name' => 'qmh.audit.view'],
            ['display_name' => 'QMH Audit View', 'module' => 'qmh', 'action' => 'view']
        );

        RolePermission::query()->updateOrCreate([
            'role' => $user->role,
            'permission_id' => $auditPermission->id,
        ]);

        QmhAudit::query()->create([
            'title' => 'Audit Modular Permission',
            'audit_type' => 'internal',
            'status' => 'draft',
            'migration_phase' => 'pivot_only',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/quality/audit')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Audit Modular Permission']);

        $this->actingAs($user)->getJson('/api/quality/rapat')->assertForbidden();
        $this->actingAs($user)->getJson('/api/quality/kum')->assertForbidden();
        $this->actingAs($user)->getJson('/api/quality/governance/summary')->assertOk();
    }

    public function test_admin_can_list_governance_api_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        QmhRapat::query()->create([
            'title' => 'Rapat Mutu',
            'meeting_type' => 'bulanan',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        QmhAudit::query()->create([
            'title' => 'Audit Lab',
            'audit_type' => 'internal',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        QmhKum::query()->create([
            'title' => 'KUM Semester',
            'year' => 2026,
            'period' => 'q2',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/quality/rapat')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Rapat Mutu']);

        $this->actingAs($user)
            ->getJson('/api/quality/audit')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Audit Lab']);

        $this->actingAs($user)
            ->getJson('/api/quality/kum')
            ->assertOk()
            ->assertJsonFragment(['title' => 'KUM Semester']);

        $this->actingAs($user)
            ->getJson('/api/quality/governance/summary')
            ->assertOk()
            ->assertJsonStructure([
                'rapat_count',
                'audit_count',
                'kum_count',
                'due_soon_count',
                'overdue_count',
                'updated_at',
            ]);
    }

    public function test_assigned_auditor_can_see_audit_in_api_list(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $auditor */
        $auditor = User::factory()->create(['role' => 'analis']);

        $audit = QmhAudit::query()->create([
            'title' => 'Audit Assignment Test',
            'audit_type' => 'internal',
            'status' => 'draft',
            'migration_phase' => 'pivot_only',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        \App\Models\QmhAuditAuditor::query()->create([
            'audit_id' => $audit->id,
            'user_id' => $auditor->id,
            'assigned_by' => $creator->id,
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['name' => 'qmh.audit.view'],
            ['display_name' => 'QMH Audit View', 'module' => 'qmh', 'action' => 'view']
        );

        RolePermission::query()->updateOrCreate([
            'role' => $auditor->role,
            'permission_id' => $permission->id,
        ]);

        $legacyPermission = Permission::query()->firstOrCreate(
            ['name' => 'qmh.view'],
            ['display_name' => 'QMH View', 'module' => 'qmh', 'action' => 'view']
        );

        RolePermission::query()->updateOrCreate([
            'role' => $auditor->role,
            'permission_id' => $legacyPermission->id,
        ]);

        $this->actingAs($auditor)
            ->getJson('/api/quality/audit')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Audit Assignment Test']);
    }

    public function test_action_item_api_supports_lifecycle_and_dependency_validation(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat API Governance',
            'meeting_type' => 'mingguan',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $first = $this->actingAs($user)
            ->postJson('/api/quality/action-items', [
                'rapat_id' => $rapat->id,
                'title' => 'Item Pertama',
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertStatus(201)
            ->json('data.id');

        $second = $this->actingAs($user)
            ->postJson('/api/quality/action-items', [
                'rapat_id' => $rapat->id,
                'title' => 'Item Kedua',
                'due_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(201)
            ->json('data.id');

        $third = $this->actingAs($user)
            ->postJson('/api/quality/action-items', [
                'rapat_id' => $rapat->id,
                'title' => 'Item Ketiga',
                'due_date' => now()->addDays(4)->toDateString(),
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($user)
            ->patchJson('/api/quality/action-items/'.$first.'/state', [
                'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', QmhRapatActionItem::STATUS_IN_PROGRESS);

        $this->actingAs($user)
            ->postJson('/api/quality/action-items/'.$first.'/dependencies', [
                'depends_on_action_item_id' => $second,
            ])
            ->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/quality/action-items/'.$second.'/dependencies', [
                'depends_on_action_item_id' => $third,
            ])
            ->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/quality/action-items/'.$second.'/dependencies', [
                'depends_on_action_item_id' => $first,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->getJson('/api/quality/action-items/'.$first.'/dependency-graph')
            ->assertOk()
            ->assertJsonPath('dependencies.0', $second)
            ->assertJsonPath('all_dependencies.0', $second)
            ->assertJsonPath('all_dependencies.1', $third)
            ->assertJsonPath('dependency_tree.0.action_item_id', $second)
            ->assertJsonPath('dependency_tree.0.children.0.action_item_id', $third);

        $this->actingAs($user)
            ->patchJson('/api/quality/action-items/'.$first.'/state', [
                'status' => QmhRapatActionItem::STATUS_OVERDUE,
            ])
            ->assertStatus(422);
    }

    private function createQmhPermissions(): void
    {
        $permissions = [
            'qmh.view',
            'qmh.create',
            'qmh.rapat.view',
            'qmh.rapat.edit',
            'qmh.audit.view',
            'qmh.kum.view',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => strtoupper($permissionName),
                    'module' => 'qmh',
                    'action' => 'view',
                ]
            );

            RolePermission::query()->updateOrCreate([
                'role' => 'admin',
                'permission_id' => $permission->id,
            ]);
        }
    }
}
