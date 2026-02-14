<?php

namespace Tests\Browser\Monitoring;

use App\Models\EnvironmentLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EnvironmentMonitoringTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_user_can_view_environment_monitoring_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->waitForText('Log Suhu & Kelembaban')
                ->assertSee('Log Suhu & Kelembaban')
                ->assertSee('Daftar Lokasi');
        });
    }

    public function test_monitoring_page_shows_location_cards(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $location = EnvironmentLocation::create([
            'name' => 'Lab Room A',
            'type' => 'room',
            'target_temp_min' => 20,
            'target_temp_max' => 25,
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user, $location) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->waitForText('Log Suhu & Kelembaban')
                ->assertSee('Daftar Lokasi')
                ->assertSee($location->name);
        });
    }

    public function test_monitoring_page_shows_input_data_button(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        EnvironmentLocation::create([
            'name' => 'Lab Room B',
            'type' => 'room',
            'target_temp_min' => 18,
            'target_temp_max' => 26,
            'is_active' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->waitForText('Log Suhu & Kelembaban')
                ->assertSee('Input Data');
        });
    }

    public function test_empty_monitoring_shows_no_locations_message(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->waitForText('Log Suhu & Kelembaban')
                ->assertSee('Belum ada lokasi monitoring');
        });
    }

    public function test_manage_locations_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment/manage')
                ->assertSee('Akses Ditolak');
        });
    }
}
