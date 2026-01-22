<?php

namespace Tests\Feature\Api;

use App\Models\MonitoringSensor;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_monitoring_data()
    {
        $sensor = MonitoringSensor::create([
            'name' => 'Freezer 1',
            'code' => 'FRZ-01',
            'type' => 'TEMPERATURE',
            'min_threshold' => -25,
            'max_threshold' => -15,
        ]);

        $this->mock(GowaClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });

        $response = $this->postJson('/api/monitoring/data', [
            'sensor_code' => 'FRZ-01',
            'value' => -20.5,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('monitoring_logs', [
            'sensor_id' => $sensor->id,
            'value' => -20.5,
        ]);

        $sensor->refresh();
        $this->assertEquals(-20.5, $sensor->last_value);
    }

    public function test_triggers_alert_when_threshold_exceeded()
    {
        $sensor = MonitoringSensor::create([
            'name' => 'Room 1',
            'code' => 'RM-01',
            'type' => 'TEMPERATURE',
            'max_threshold' => 25,
        ]);

        $this->mock(GowaClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once();
        });

        $response = $this->postJson('/api/monitoring/data', [
            'sensor_code' => 'RM-01',
            'value' => 28.5,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('monitoring_alerts', [
            'sensor_id' => $sensor->id,
            'type' => 'HIGH_TEMP',
            'status' => 'OPEN',
        ]);
    }
}
