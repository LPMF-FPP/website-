<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringAlert;
use App\Models\MonitoringSensor;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function __construct(
        protected GowaClient $whatsapp
    ) {}

    public function checkThresholds(MonitoringSensor $sensor, float $value): void
    {
        if (! $sensor->is_active) {
            return;
        }

        $type = null;
        $threshold = null;

        if ($sensor->max_threshold !== null && $value > $sensor->max_threshold) {
            $type = 'HIGH_TEMP';
            $threshold = $sensor->max_threshold;
        } elseif ($sensor->min_threshold !== null && $value < $sensor->min_threshold) {
            $type = 'LOW_TEMP';
            $threshold = $sensor->min_threshold;
        }

        if ($type) {
            $this->createAlert($sensor, $type, $value, $threshold);
        }
    }

    protected function createAlert(MonitoringSensor $sensor, string $type, float $value, float $threshold): void
    {
        $existing = MonitoringAlert::where('sensor_id', $sensor->id)
            ->where('type', $type)
            ->where('status', 'OPEN')
            ->first();

        if ($existing) {
            $existing->update([
                'value' => $value,
                'updated_at' => now(),
            ]);

            return;
        }

        $alert = MonitoringAlert::create([
            'sensor_id' => $sensor->id,
            'type' => $type,
            'value' => $value,
            'threshold' => $threshold,
            'status' => 'OPEN',
        ]);

        $this->sendNotification($alert);
    }

    protected function sendNotification(MonitoringAlert $alert): void
    {
        $sensor = $alert->sensor;
        $message = "⚠️ *MONITORING ALERT*\n\n";
        $message .= "Sensor: {$sensor->name}\n";
        $message .= "Location: {$sensor->location}\n";
        $message .= "Type: {$alert->type}\n";
        $message .= "Value: {$alert->value}°C\n";
        $message .= "Threshold: {$alert->threshold}°C\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        $adminNumber = settings('notifications.whatsapp.admin_number', '6285956592404');

        try {
            $this->whatsapp->sendMessage($adminNumber.'@s.whatsapp.net', $message);
        } catch (\Exception $e) {
            Log::error('Failed to send monitoring alert: '.$e->getMessage());
        }
    }
}
