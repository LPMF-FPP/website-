<?php

namespace Tests\Feature\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_can_view_dashboard_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $this->createQmhPermissions();

        $response = $this->actingAs($user)->get('/quality');

        $response->assertOk();
        $response->assertViewIs('quality.dashboard');
        $response->assertSee('Dashboard QMH');
        $response->assertSee('Work Queue Saya');
    }

    public function test_api_returns_pulse_stats(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $this->createQmhPermissions();

        // Seed some data
        $doc = QmhDocument::factory()->create(['clause' => 7]);
        $revision = QmhDocumentRevision::factory()->create([
            'document_id' => $doc->id,
            'status' => 'in_review',
            'submitted_at' => now(),
            'diperiksa_oleh' => $user->id,
        ]);
        $doc->update(['current_revision_id' => $revision->id]);

        $response = $this->actingAs($user)->getJson('/api/quality/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'clauses' => [
                    4, 5, 6, 7, 8,
                ],
                'global_pulse',
                'user_tasks',
            ])
            ->assertJsonPath('clauses.7.review', 1)
            ->assertJsonPath('user_tasks', 1);
    }

    private function createQmhPermissions(): void
    {
        // Mock permissions if needed, or rely on role check in controller/middleware
        // In existing tests, we saw 'qmh.view' permission being created.
        // Let's create it properly.
        \App\Models\Permission::firstOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'View QMH',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        \App\Models\RolePermission::firstOrCreate([
            'role' => 'admin',
            'permission_id' => \App\Models\Permission::where('name', 'qmh.view')->first()->id,
        ]);
    }
}
