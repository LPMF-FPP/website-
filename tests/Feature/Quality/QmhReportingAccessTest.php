<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhReportingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_api_summary_requires_authentication(): void
    {
        $this->getJson('/api/quality/dashboard/summary')
            ->assertUnauthorized();
    }

    public function test_api_summary_forbids_user_without_qmh_report_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->getJson('/api/quality/dashboard/summary')
            ->assertForbidden();
    }

    public function test_api_summary_allows_user_with_qmh_report_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->getJson('/api/quality/dashboard/summary')
            ->assertOk();
    }

    public function test_web_report_page_requires_qmh_report_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/reports')
            ->assertRedirect('/dashboard');
    }

    public function test_web_report_page_allows_user_with_qmh_report_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/reports')
            ->assertOk()
            ->assertViewIs('quality.reports.index')
            ->assertSee('Laporan QMH')
            ->assertSee('Riwayat Revisi')
            ->assertSee('Riwayat Unduhan')
            ->assertSee('Distribusi Terkendali');
    }

    private function createQmhPermissions(): void
    {
        $viewPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'Lihat Quality Management Hub',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        $reportPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.report'],
            [
                'display_name' => 'Lihat Laporan Quality Management Hub',
                'module' => 'qmh',
                'action' => 'report',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $reportPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'investigator',
            'permission_id' => $viewPermission->id,
        ]);
    }
}
