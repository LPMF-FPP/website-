<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhTemplateImmutableVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
    }

    public function test_update_route_is_removed_and_returns_404(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template SOP v1',
            'clause' => 5,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => ['content_html' => '<p>V1</p>'],
        ]);

        $this->actingAs($user)
            ->patch('/quality/templates/'.$template->id, [
                'name' => 'Template SOP v2',
                'clause' => 5,
                'doc_type' => 'sop',
                'content_html' => '<p>V2</p>',
            ])
            ->assertNotFound();
    }

    public function test_activate_route_is_removed_and_returns_404(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $old = QmhTemplate::query()->create([
            'name' => 'Template SOP v1',
            'clause' => 6,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => false,
            'archived_at' => now()->subDay(),
            'metadata' => ['content_html' => '<p>V1</p>'],
        ]);

        $this->actingAs($user)
            ->patch('/quality/templates/'.$old->id.'/activate')
            ->assertNotFound();
    }

    private function createPermissions(): void
    {
        $templateManage = Permission::query()->updateOrCreate(
            ['name' => 'qmh.template.manage'],
            [
                'display_name' => 'Kelola Template Quality Management Hub',
                'module' => 'qmh',
                'action' => 'template-manage',
            ]
        );

        $view = Permission::query()->updateOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'Lihat Quality Management Hub',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        foreach ([$templateManage, $view] as $permission) {
            RolePermission::query()->updateOrCreate([
                'role' => 'admin',
                'permission_id' => $permission->id,
            ]);
        }
    }
}
