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

    public function test_update_publishes_new_template_version_instead_of_mutating_original(): void
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
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template SOP v2',
                'clause' => 5,
                'doc_type' => 'sop',
                'content_html' => '<p>V2</p>',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertNotNull($template->archived_at);

        $published = QmhTemplate::query()
            ->where('clause', 5)
            ->where('doc_type', 'sop')
            ->where('name', 'Template SOP v2')
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertNotSame($template->id, $published->id);
        $this->assertSame(2, (int) $published->version);
        $this->assertTrue($published->is_active);
        $this->assertNull($published->archived_at);
        $this->assertSame('<p>V2</p>', data_get($published->metadata, 'content_html'));
    }

    public function test_activate_supports_rollback_to_older_template_version(): void
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

        $current = QmhTemplate::query()->create([
            'name' => 'Template SOP v2',
            'clause' => 6,
            'doc_type' => 'sop',
            'version' => 2,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => ['content_html' => '<p>V2</p>'],
        ]);

        $this->actingAs($user)
            ->patch(route('quality.templates.activate', $old))
            ->assertRedirect(route('quality.templates.index'));

        $this->assertTrue($old->fresh()->is_active);
        $this->assertNull($old->fresh()->archived_at);
        $this->assertFalse($current->fresh()->is_active);
        $this->assertNotNull($current->fresh()->archived_at);
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
