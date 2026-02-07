<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_quick_action_widget()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pengeluaran Cepat'); // Quick Issue tab
        $response->assertSee('Penerimaan Cepat'); // Quick Receipt tab
        $response->assertSee('Transfer Cepat'); // Quick Transfer tab
    }
}
