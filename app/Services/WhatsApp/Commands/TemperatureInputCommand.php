<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Models\User;
use Carbon\Carbon;

class TemperatureInputCommand
{
    public function execute(string $fromJid, array $params): string
    {
        // If no params, list sensors and latest reading
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
            $response .= "\nKetik `/suhu {lokasi} {nilai} {pagi/siang}` untuk input.";

            return $response;
        }

        if (count($params) < 2) {
            return "⚠️ Format salah.\nGunakan: /suhu {nama_lokasi} {nilai_suhu} {pagi/siang}\nContoh: /suhu R01 24.5 pagi";
        }

        $locationName = $params[0];
        $value = floatval($params[1]);
        $period = isset($params[2]) ? strtolower($params[2]) : 'pagi';

        $location = EnvironmentLocation::where('name', 'LIKE', "%{$locationName}%")
            ->where('is_active', true)
            ->first();

        if (! $location) {
            return "⚠️ Lokasi '{$locationName}' tidak ditemukan.";
        }

        $time = match ($period) {
            'siang', 'sore' => '14:00:00',
            default => '08:00:00',
        };

        $measuredAt = Carbon::now()->setTimeFromTimeString($time);

        $phone = explode('@', $fromJid)[0];
        // User lookup skipped as phone_number column does not exist
        // $user = User::where('phone_number', $phone)->first();

        try {
            EnvironmentReading::create([
                'location_id' => $location->id,
                'measured_at' => $measuredAt,
                'temperature_c' => $value,
                'humidity_rh' => null,
                'entered_by' => null, // System/Bot
                'source' => 'whatsapp',
                'notes' => "Input via WhatsApp ($period) - Sender: $phone",
            ]);

            return "✅ Suhu tercatat.\nLokasi: {$location->name}\nNilai: {$value}°C\nWaktu: {$period}";
        } catch (\Exception $e) {
            return '❌ Gagal menyimpan data: '.$e->getMessage();
        }
    }
}
