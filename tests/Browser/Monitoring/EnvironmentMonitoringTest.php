<?php

namespace Tests\Browser\Monitoring;

use App\Enums\EnvironmentLocationType;
use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EnvironmentMonitoringTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_environment_locations_list(): void
    {
        $user = User::factory()->create();
        $location = EnvironmentLocation::factory()->create(['name' => 'Lab Room A']);

        $this->browse(function (Browser $browser) use ($user, $location) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->assertSee('Environment Monitoring')
                ->assertSee($location->name)
                ->assertPresent('table');
        });
    }

    public function test_user_can_create_environment_reading(): void
    {
        $user = User::factory()->create();
        $location = EnvironmentLocation::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $location) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->assertPresent('button:contains("Add Reading")')
                ->click('button:contains("Add Reading")')
                ->waitFor('.reading-modal')
                ->select('environment_location_id', $location->id)
                ->type('temperature', '25.5')
                ->type('humidity', '60.0')
                ->press('Save Reading')
                ->waitForText('Reading saved successfully')
                ->assertSee('Reading saved successfully');

            $this->assertDatabaseHas('environment_readings', [
                'environment_location_id' => $location->id,
                'temperature' => '25.5',
                'humidity' => '60.0',
            ]);
        });
    }

    public function test_user_can_view_reading_history(): void
    {
        $user = User::factory()->create();
        $location = EnvironmentLocation::factory()->create();
        $reading = EnvironmentReading::factory()->create([
            'environment_location_id' => $location->id,
            'temperature' => 26.0,
            'humidity' => 58.0,
        ]);

        $this->browse(function (Browser $browser) use ($user, $location, $reading) {
            $browser->loginAs($user)
                ->visit("/monitoring/environment/locations/{$location->id}/history")
                ->assertSee('Reading History')
                ->assertSee('26.0')
                ->assertSee('58.0')
                ->assertPresent('.reading-chart');
        });
    }

    public function test_user_receives_temperature_threshold_alert(): void
    {
        $user = User::factory()->create();
        $location = EnvironmentLocation::factory()->create([
            'target_temp_min' => 20,
            'target_temp_max' => 25,
        ]);
        $reading = EnvironmentReading::factory()->create([
            'environment_location_id' => $location->id,
            'temperature' => 30.0,
            'humidity' => 60.0,
        ]);

        $this->browse(function (Browser $browser) use ($user, $location) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->assertSee('Temperature Alert')
                ->assertSee($location->name)
                ->assertPresent('.alert-danger');
        });
    }

    public function test_user_can_monitor_humidity_levels(): void
    {
        $user = User::factory()->create();
        $location = EnvironmentLocation::factory()->create([
            'target_hum_min' => 40,
            'target_hum_max' => 60,
        ]);

        $reading = EnvironmentReading::factory()->create([
            'environment_location_id' => $location->id,
            'temperature' => 23.0,
            'humidity' => 75.0,
        ]);

        $this->browse(function (Browser $browser) use ($user, $location) {
            $browser->loginAs($user)
                ->visit('/monitoring/environment')
                ->assertSee($location->name)
                ->assertSee('Humidity Alert')
                ->assertPresent('.humidity-warning');
        });
    }
}
