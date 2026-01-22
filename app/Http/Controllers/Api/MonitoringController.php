<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoringLog;
use App\Models\MonitoringSensor;
use App\Services\Monitoring\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    public function __construct(
        protected AlertService $alertService
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sensor_code' => 'required|string|exists:monitoring_sensors,code',
            'value' => 'required|numeric',
            'humidity' => 'nullable|numeric',
            'recorded_at' => 'nullable|date',
        ]);

        try {
            $sensor = MonitoringSensor::where('code', $validated['sensor_code'])->firstOrFail();

            $log = MonitoringLog::create([
                'sensor_id' => $sensor->id,
                'value' => $validated['value'],
                'secondary_value' => $validated['humidity'] ?? null,
                'recorded_at' => $validated['recorded_at'] ?? now(),
            ]);

            $sensor->update([
                'last_reading_at' => $log->recorded_at,
                'last_value' => $log->value,
            ]);

            $this->alertService->checkThresholds($sensor, $log->value);

            return response()->json([
                'status' => 'success',
                'data' => $log,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Monitoring data error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
