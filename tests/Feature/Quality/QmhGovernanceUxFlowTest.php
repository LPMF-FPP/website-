<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhKum;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhGovernanceUxFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seedGovernancePermissions();
    }

    public function test_workspace_to_detail_status_update_flow_is_reflected(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Governance',
            'meeting_type' => 'mingguan',
            'status' => 'scheduled',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $actionItem = QmhRapatActionItem::query()->create([
            'rapat_id' => $rapat->id,
            'title' => 'Tindak lanjut UX governance',
            'status' => QmhRapatActionItem::STATUS_OPEN,
            'due_date' => now()->addDays(3)->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/quality/governance')
            ->assertOk()
            ->assertSee('Action Items Due Soon')
            ->assertSee('Tindak lanjut UX governance');

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_RESOLVED,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_VERIFIED,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch('/quality/rapat/'.$rapat->id.'/action-items/'.$actionItem->id.'/status', [
                'status' => QmhRapatActionItem::STATUS_CLOSED,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/quality/governance')
            ->assertOk()
            ->assertDontSee('Tindak lanjut UX governance');
    }

    public function test_kum_decisions_generate_staff_tasks_from_web_flow(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $kum = QmhKum::query()->create([
            'title' => 'KUM Integrasi',
            'year' => 2026,
            'period' => 'q2',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/quality/kum/'.$kum->id.'/action-items', [
                'decisions' => [
                    [
                        'item' => 'Follow up temuan audit internal',
                        'description' => 'Koordinasikan ke unit terkait',
                        'due_date' => now()->addDays(5)->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect('/quality/kum/'.$kum->id);

        $this->assertDatabaseHas('staff_tasks', [
            'title' => 'Follow up temuan audit internal',
            'source_module' => 'qmh',
            'source_ref_type' => 'qmh_kum',
            'source_ref_id' => $kum->id,
            'assigned_to' => $user->id,
        ]);
    }

    public function test_kum_action_item_api_rejects_assignee_without_governance_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        /** @var User $outsider */
        $outsider = User::factory()->create(['role' => 'investigator']);

        $kum = QmhKum::query()->create([
            'title' => 'KUM Integrasi API',
            'year' => 2026,
            'period' => 'q3',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/quality/kum/'.$kum->id.'/action-items', [
                'decisions' => [
                    [
                        'item' => 'Tugas yang harus ditolak',
                        'assignee_id' => $outsider->id,
                        'due_date' => now()->addDay()->toDateString(),
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    private function seedGovernancePermissions(): void
    {
        $permissions = [
            'qmh.view',
            'qmh.create',
            'qmh.rapat.view',
            'qmh.rapat.create',
            'qmh.rapat.edit',
            'qmh.kum.view',
            'qmh.kum.create',
            'qmh.kum.edit',
            'qmh.audit.view',
            'action-item:verify',
            'action-item:close',
            'action-item:reopen',
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
