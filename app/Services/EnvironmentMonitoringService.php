<?php

namespace App\Services;

use App\Enums\ReadingSource;
use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use function settings;

class EnvironmentMonitoringService
{
    public function getSettings(): array
    {
        return [
            'enabled' => (bool) settings('monitoring_logging.environment.enabled', false),
            'work_start' => settings('monitoring_logging.environment.work_start', '07:00'),
            'work_end' => settings('monitoring_logging.environment.work_end', '15:00'),
            'work_days' => settings('monitoring_logging.environment.work_days', [1, 2, 3, 4, 5]),
            'window_morning_start' => settings('monitoring_logging.environment.window_morning_start', '07:00'),
            'window_morning_end' => settings('monitoring_logging.environment.window_morning_end', '09:00'),
            'window_afternoon_start' => settings('monitoring_logging.environment.window_afternoon_start', '12:00'),
            'window_afternoon_end' => settings('monitoring_logging.environment.window_afternoon_end', '14:00'),
            'humidity_enabled' => (bool) settings('monitoring_logging.environment.humidity_enabled', false),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->getSettings()['enabled'];
    }

    public function isWorkDay(Carbon $date): bool
    {
        $settings = $this->getSettings();
        $workDays = is_array($settings['work_days']) ? $settings['work_days'] : [1, 2, 3, 4, 5];

        return in_array($date->dayOfWeekIso, $workDays, true);
    }

    public function getActiveLocations(): Collection
    {
        return EnvironmentLocation::where('is_active', true)->orderBy('name')->get();
    }

    public function getActiveWindow(Carbon $datetime): ?array
    {
        $settings = $this->getSettings();

        if (! $this->isWorkDay($datetime)) {
            return null;
        }

        $time = $datetime->format('H:i');

        $morningStart = $settings['window_morning_start'];
        $morningEnd = $settings['window_morning_end'];
        $afternoonStart = $settings['window_afternoon_start'];
        $afternoonEnd = $settings['window_afternoon_end'];

        if ($time >= $morningStart && $time <= $morningEnd) {
            return [
                'name' => 'morning',
                'label' => 'Pagi',
                'start' => $morningStart,
                'end' => $morningEnd,
            ];
        }

        if ($time >= $afternoonStart && $time <= $afternoonEnd) {
            return [
                'name' => 'afternoon',
                'label' => 'Siang',
                'start' => $afternoonStart,
                'end' => $afternoonEnd,
            ];
        }

        return null;
    }

    public function canInputForWindow(string $windowName, Carbon $datetime): bool
    {
        $settings = $this->getSettings();
        $time = $datetime->format('H:i');

        if ($windowName === 'morning') {
            $morningEnd = $settings['window_morning_end'];

            return $time <= $morningEnd;
        }

        if ($windowName === 'afternoon') {
            $afternoonEnd = $settings['window_afternoon_end'];

            return $time <= $afternoonEnd;
        }

        return false;
    }

    public function getDueListForUser(?User $user, ?Carbon $date = null): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        $date = $date ?? Carbon::now();

        if (! $this->isWorkDay($date)) {
            return collect();
        }

        $locations = $this->getActiveLocations();
        $activeWindow = $this->getActiveWindow($date);

        return $locations->map(function (EnvironmentLocation $location) use ($date, $activeWindow) {
            $status = $this->getLocationStatus($location, $date);

            return [
                'location' => $location,
                'status' => $status['status'],
                'active_window' => $activeWindow,
                'morning_filled' => $status['morning_filled'],
                'afternoon_filled' => $status['afternoon_filled'],
                'can_input_morning' => $this->canInputForWindow('morning', $date),
                'can_input_afternoon' => $this->canInputForWindow('afternoon', $date),
            ];
        })->filter(function ($item) {
            return in_array($item['status'], ['due', 'overdue']);
        });
    }

    public function getLocationStatus(EnvironmentLocation $location, Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();
        $settings = $this->getSettings();

        $morningStart = Carbon::parse($date->format('Y-m-d').' '.$settings['window_morning_start']);
        $morningEnd = Carbon::parse($date->format('Y-m-d').' '.$settings['window_morning_end']);
        $afternoonStart = Carbon::parse($date->format('Y-m-d').' '.$settings['window_afternoon_start']);
        $afternoonEnd = Carbon::parse($date->format('Y-m-d').' '.$settings['window_afternoon_end']);

        $morningReading = EnvironmentReading::where('location_id', $location->id)
            ->whereBetween('measured_at', [$morningStart, $morningEnd])
            ->whereNull('correction_of_id')
            ->first();

        $afternoonReading = EnvironmentReading::where('location_id', $location->id)
            ->whereBetween('measured_at', [$afternoonStart, $afternoonEnd])
            ->whereNull('correction_of_id')
            ->first();

        $morningFilled = $morningReading !== null;
        $afternoonFilled = $afternoonReading !== null;

        $now = Carbon::now();
        $currentTime = $now->format('H:i');

        $status = 'complete';

        if (! $morningFilled && $currentTime > $settings['window_morning_end']) {
            $status = 'overdue';
        } elseif (! $morningFilled && $currentTime >= $settings['window_morning_start'] && $currentTime <= $settings['window_morning_end']) {
            $status = 'due';
        } elseif (! $afternoonFilled && $currentTime > $settings['window_afternoon_end']) {
            $status = 'overdue';
        } elseif (! $afternoonFilled && $currentTime >= $settings['window_afternoon_start'] && $currentTime <= $settings['window_afternoon_end']) {
            $status = 'due';
        } elseif (! $morningFilled || ! $afternoonFilled) {
            $status = 'pending';
        }

        return [
            'status' => $status,
            'morning_filled' => $morningFilled,
            'afternoon_filled' => $afternoonFilled,
            'morning_reading' => $morningReading,
            'afternoon_reading' => $afternoonReading,
        ];
    }

    public function validateReadingData(EnvironmentLocation $location, array $data): array
    {
        $errors = [];
        $settings = $this->getSettings();

        if (! isset($data['temperature_c']) || $data['temperature_c'] === null || $data['temperature_c'] === '') {
            $errors['temperature_c'] = 'Suhu wajib diisi.';
        } else {
            $temp = (float) $data['temperature_c'];
            if ($temp < -50 || $temp > 100) {
                $errors['temperature_c'] = 'Suhu tidak valid (harus antara -50 dan 100).';
            }
        }

        if ($settings['humidity_enabled'] || $location->target_humidity_min !== null) {
            if (! isset($data['humidity_rh']) || $data['humidity_rh'] === null || $data['humidity_rh'] === '') {
                $errors['humidity_rh'] = 'Kelembaban wajib diisi untuk lokasi ini.';
            } else {
                $hum = (float) $data['humidity_rh'];
                if ($hum < 0 || $hum > 100) {
                    $errors['humidity_rh'] = 'Kelembaban tidak valid (harus antara 0 dan 100).';
                }
            }
        }

        return $errors;
    }

    public function detectOutOfRange(EnvironmentReading $reading, EnvironmentLocation $location): array
    {
        $result = [
            'temperature_out_of_range' => false,
            'humidity_out_of_range' => false,
            'messages' => [],
        ];

        if ($location->target_temp_min !== null && $reading->temperature_c < $location->target_temp_min) {
            $result['temperature_out_of_range'] = true;
            $result['messages'][] = "Suhu {$reading->temperature_c}°C di bawah batas minimum {$location->target_temp_min}°C.";
        }

        if ($location->target_temp_max !== null && $reading->temperature_c > $location->target_temp_max) {
            $result['temperature_out_of_range'] = true;
            $result['messages'][] = "Suhu {$reading->temperature_c}°C di atas batas maksimum {$location->target_temp_max}°C.";
        }

        if ($reading->humidity_rh !== null) {
            if ($location->target_humidity_min !== null && $reading->humidity_rh < $location->target_humidity_min) {
                $result['humidity_out_of_range'] = true;
                $result['messages'][] = "Kelembaban {$reading->humidity_rh}% di bawah batas minimum {$location->target_humidity_min}%.";
            }

            if ($location->target_humidity_max !== null && $reading->humidity_rh > $location->target_humidity_max) {
                $result['humidity_out_of_range'] = true;
                $result['messages'][] = "Kelembaban {$reading->humidity_rh}% di atas batas maksimum {$location->target_humidity_max}%.";
            }
        }

        return $result;
    }

    public function createReading(EnvironmentLocation $location, array $data, User $user): EnvironmentReading
    {
        $reading = new EnvironmentReading;
        $reading->location_id = $location->id;
        $reading->measured_at = $data['measured_at'] ?? Carbon::now();
        $reading->temperature_c = (float) $data['temperature_c'];
        $reading->humidity_rh = isset($data['humidity_rh']) && $data['humidity_rh'] !== '' ? (float) $data['humidity_rh'] : null;
        $reading->entered_by = $user->id;
        $reading->source = $data['source'] ?? ReadingSource::MANUAL;
        $reading->notes = $data['notes'] ?? null;
        $reading->save();

        return $reading;
    }

    public function createCorrection(EnvironmentReading $originalReading, array $correctedData, string $reason, User $user): EnvironmentReading
    {
        $correction = new EnvironmentReading;
        $correction->location_id = $originalReading->location_id;
        $correction->measured_at = $originalReading->measured_at;
        $correction->temperature_c = (float) $correctedData['temperature_c'];
        $correction->humidity_rh = isset($correctedData['humidity_rh']) && $correctedData['humidity_rh'] !== '' ? (float) $correctedData['humidity_rh'] : null;
        $correction->entered_by = $user->id;
        $correction->source = ReadingSource::MANUAL;
        $correction->notes = $correctedData['notes'] ?? null;
        $correction->correction_of_id = $originalReading->id;
        $correction->correction_reason = $reason;
        $correction->save();

        return $correction;
    }

    public function getReadingsForMonth(int $locationId, int $year, int $month): Collection
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        return EnvironmentReading::where('location_id', $locationId)
            ->whereBetween('measured_at', [$startOfMonth, $endOfMonth])
            ->with('enteredByUser')
            ->orderBy('measured_at')
            ->get();
    }
}
