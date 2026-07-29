<?php

namespace Tests\Feature\Dashboard;

use App\Models\Suspect;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
    }

    public function test_dashboard_displays_disposisi_table(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(5),
        ]);
        Suspect::factory()->create([
            'test_request_id' => $request->id,
            'name' => 'JOHN DOE TEST',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Rekapitulasi Disposisi');
        $response->assertSee('JOHN DOE TEST');
    }

    public function test_dashboard_displays_hero_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Kecepatan');
        $response->assertSee('Kepuasan');
    }

    public function test_dashboard_counts_pending_requests_instead_of_pending_samples(): void
    {
        $user = User::factory()->create();

        foreach (['submitted', 'verified', 'received'] as $status) {
            TestRequest::factory()->create(['status' => $status]);
        }

        foreach (['in_testing', 'analysis', 'quality_check', 'ready_for_delivery', 'completed', 'rejected'] as $status) {
            TestRequest::factory()->create(['status' => $status]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats): bool => $stats['pending_requests'] === 3);
        $response->assertSee('data-stat-key="pending_requests"', false);
        $response->assertSeeText('Permintaan Pending');
        $response->assertDontSeeText('Sampel Pending');
    }

    public function test_dashboard_disposisi_search_uses_static_alpine_expression(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(1),
        ]);
        Suspect::factory()->create([
            'test_request_id' => $request->id,
            'name' => 'REGRESSION TEST TSK',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('$el.textContent.toLowerCase().includes(search.toLowerCase())', false);
    }
}
