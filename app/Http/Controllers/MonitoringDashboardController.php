<?php

namespace App\Http\Controllers;

use App\Models\MonitoringAlert;
use App\Models\MonitoringSensor;
use Illuminate\Http\Request;

class MonitoringDashboardController extends Controller
{
    public function index()
    {
        $sensors = MonitoringSensor::with(['alerts' => function ($query) {
            $query->where('status', 'OPEN')->latest();
        }])->orderBy('name')->get();

        $activeAlerts = MonitoringAlert::with('sensor')
            ->where('status', 'OPEN')
            ->latest()
            ->get();

        return view('monitoring.sensors.index', [
            'sensors' => $sensors,
            'activeAlerts' => $activeAlerts,
        ]);
    }
}
