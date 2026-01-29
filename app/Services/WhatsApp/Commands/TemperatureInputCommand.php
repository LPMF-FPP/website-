<?php

namespace App\Services\WhatsApp\Commands;

use App\Enums\ReadingSource;
use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Models\User;
use App\Services\EnvironmentMonitoringService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TemperatureInputCommand
{
    public function __construct(
        private EnvironmentMonitoringService $service
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        Log::info('TemperatureCommand triggered', ['from' => $fromJid, 'params' => $params]);

        if (empty($params)) {
            $locations = EnvironmentLocation::where('is_active', true)
                ->with(['readings' => function ($q) {
                    $q->latest('measured_at')->limit(1);
                }])
                ->orderBy('name')
                ->get();

            if ($locations->isEmpty()) {
                return "🌡️ *Data Sensor Kosong*\nBelum ada lokasi monitoring.";
            }

            $response = "🌡️ *DAFTAR SUHU TERKINI*\n\n";
            foreach ($locations as $loc) {
                $last = $loc->readings->first();
                $val = $last ? "{$last->temperature_c}°C" : '-';
                $time = $last ? $last->measured_at->format('H:i') : '';

                $response .= "• {$loc->name}: {$val} {$time}\n";
            }
            $response .= "\nKetik `/suhu {lokasi} {suhu} {kelembaban} {am/pm}` untuk input.";

            return $response;
        }

        // --- NEW INTELLIGENT PARSING ---

        $temp = null;
        $humidity = null;
        $period = 'am'; // Default to morning (AM)
        $nameParts = [];

        foreach ($params as $param) {
            // Check if numeric (Value)
            // Allow comma as decimal separator
            $cleanParam = str_replace(',', '.', $param);
            if (is_numeric($cleanParam)) {
                if ($temp === null) {
                    $temp = floatval($cleanParam);
                } elseif ($humidity === null) {
                    $humidity = floatval($cleanParam);
                }

                continue;
            }

            // Check if period keyword
            $lowerParam = strtolower($param);
            if (in_array($lowerParam, ['am', 'pm', 'pagi', 'siang', 'sore'])) {
                $period = $lowerParam;

                continue;
            }

            // Otherwise, part of location name
            $nameParts[] = $param;
        }

        if ($temp === null) {
            Log::warning('TemperatureCommand: No temperature found', ['params' => $params]);

            return "⚠️ Nilai suhu tidak ditemukan.\nContoh: `/suhu Ruang GC 24.5 60.0 am`";
        }

        // Humidity is now OPTIONAL during parsing, but validated later based on location

        if (empty($nameParts)) {
            Log::warning('TemperatureCommand: No location name found', ['params' => $params]);

            return "⚠️ Nama lokasi tidak ditemukan.\nContoh: `/suhu Ruang GC 24.5 60.0 am`";
        }

        // --- FUZZY MATCHING ---

        $inputName = strtolower(implode(' ', $nameParts));
        // Normalize: remove special chars for comparison
        $normalizedInput = preg_replace('/[^a-z0-9]/', '', $inputName);

        $activeLocations = EnvironmentLocation::where('is_active', true)->get();
        $candidates = [];

        foreach ($activeLocations as $loc) {
            $dbName = strtolower($loc->name);
            $normalizedDb = preg_replace('/[^a-z0-9]/', '', $dbName);

            // Check if input is contained in DB name OR DB name is contained in input
            // Example: "gc ms" (gcms) in "Ruang-GC-MS" (ruanggcms) -> Match
            // Example: "ruang" (ruang) in "Ruang Staff" (ruangstaff) -> Match
            if (str_contains($normalizedDb, $normalizedInput) || str_contains($normalizedInput, $normalizedDb)) {
                $candidates[] = $loc;
            }
        }

        if (count($candidates) === 0) {
            return "⚠️ Lokasi '{$inputName}' tidak ditemukan.";
        }

        if (count($candidates) > 1) {
            // Check for exact match to resolve ambiguity
            foreach ($candidates as $candidate) {
                if (strtolower($candidate->name) === $inputName) {
                    $location = $candidate;
                    $candidates = [$candidate]; // Force single result
                    break;
                }
            }

            if (count($candidates) > 1) {
                $list = implode("\n• ", array_column($candidates, 'name'));

                return "⚠️ Pencarian '{$inputName}' terlalu umum. Cocok dengan:\n• {$list}\n\nMohon lebih spesifik.";
            }
        }

        $location = $candidates[0];

        // --- VALIDATE HUMIDITY REQUIREMENT ---
        if ($humidity === null) {
            // Check if humidity is required for this location
            // Using accessor via model fix
            if ($location->target_humidity_min !== null) {
                return "⚠️ Kelembaban wajib diisi untuk lokasi ini.\nContoh: `/suhu {$inputName} {$temp} 60.0 {$period}`";
            }
        }

        // --- SAVE DATA ---

        $time = match ($period) {
            'siang', 'sore', 'pm' => '14:00:00',
            default => '08:00:00',
        };

        $measuredAt = Carbon::now()->setTimeFromTimeString($time);
        $phone = explode('@', $fromJid)[0];

        // Lookup user by phone or fallback to Admin (ID 443)
        $user = User::where('phone', $phone)->orWhere('phone', '0'.$phone)->first();
        $enteredBy = $user?->id ?? 443; // Default to Admin LPMF if user not found

        try {
            Log::info('TemperatureCommand: Saving data', [
                'location_id' => $location->id,
                'temp' => $temp,
                'humidity' => $humidity,
                'source' => ReadingSource::MANUAL,
                'entered_by' => $enteredBy,
            ]);

            $reading = EnvironmentReading::create([
                'location_id' => $location->id,
                'measured_at' => $measuredAt,
                'temperature_c' => $temp,
                'humidity_rh' => $humidity,
                'entered_by' => $enteredBy,
                'source' => ReadingSource::MANUAL,
                'notes' => "Input via WhatsApp ($period) - Sender: $phone",
            ]);

            // Check for warnings
            $outOfRange = $this->service->detectOutOfRange($reading, $location);
            $warningMsg = '';

            if ($outOfRange['temperature_out_of_range'] || $outOfRange['humidity_out_of_range']) {
                $warningMsg = "\n\n⚠️ *PERINGATAN:*";
                foreach ($outOfRange['messages'] as $msg) {
                    $warningMsg .= "\n- ".$msg;
                }
            }

            return "✅ Data tercatat.\nLokasi: {$location->name}\nSuhu: {$temp}°C\nKelembaban: {$humidity}%{$warningMsg}";
        } catch (\Exception $e) {
            return '❌ Gagal menyimpan data: '.$e->getMessage();
        }
    }
}
