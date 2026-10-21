<?php

namespace Tests\Feature\Reports;

use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MonthlyLogCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_environment_csv_uses_locale_safe_decimal_values_for_excel(): void
    {
        Gate::define('manage-settings', fn (): bool => true);

        $user = User::factory()->create();
        $location = EnvironmentLocation::create([
            'name' => 'Chiller',
            'type' => 'fridge',
        ]);

        EnvironmentReading::create([
            'location_id' => $location->id,
            'measured_at' => '2026-01-28 08:00:00',
            'temperature_c' => '3.70',
            'humidity_rh' => '41.00',
            'entered_by' => $user->id,
            'source' => 'manual',
        ]);

        EnvironmentReading::create([
            'location_id' => $location->id,
            'measured_at' => '2026-01-28 14:00:00',
            'temperature_c' => '-2.50',
            'humidity_rh' => null,
            'entered_by' => $user->id,
            'source' => 'manual',
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.monthly-logs.environment.csv', [
                'location_id' => $location->id,
                'month' => '2026-01',
            ]));

        $response->assertOk();

        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        $rows = array_map(
            fn (string $line): array => str_getcsv($line, ';'),
            array_filter(explode("\n", trim($csv)))
        );

        $this->assertSame('Suhu (°C)', $rows[0][3]);
        $this->assertSame('Kelembaban (%RH)', $rows[0][4]);
        $this->assertSame('3,70', $rows[1][3]);
        $this->assertSame('41,00', $rows[1][4]);
        $this->assertSame('-2,50', $rows[2][3]);
        $this->assertSame('-', $rows[2][4]);
    }
}
