<?php

namespace App\Http\Controllers;

use App\Models\EnvironmentLocation;
use App\Models\EnvironmentReading;
use App\Services\EnvironmentMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnvironmentMonitoringController extends Controller
{
    public function __construct(
        protected EnvironmentMonitoringService $service
    ) {}

    public function index(Request $request)
    {
        $locations = $this->service->getActiveLocations();
        $settings = $this->service->getSettings();
        $today = Carbon::now();
        $activeWindow = $this->service->getActiveWindow($today);

        $locationsWithStatus = $locations->map(function (EnvironmentLocation $location) use ($today) {
            $status = $this->service->getLocationStatus($location, $today);

            return [
                'location' => $location,
                'status' => $status['status'],
                'morning_filled' => $status['morning_filled'],
                'afternoon_filled' => $status['afternoon_filled'],
                'morning_reading' => $status['morning_reading'],
                'afternoon_reading' => $status['afternoon_reading'],
            ];
        });

        return view('monitoring.environment.index', [
            'locations' => $locationsWithStatus,
            'settings' => $settings,
            'activeWindow' => $activeWindow,
            'today' => $today,
            'isWorkDay' => $this->service->isWorkDay($today),
        ]);
    }

    public function storeReading(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:environment_locations,id'],
            'temperature_c' => ['required', 'numeric', 'min:-50', 'max:100'],
            'humidity_rh' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'measured_at' => ['nullable', 'date'],
        ]);

        $location = EnvironmentLocation::findOrFail($validated['location_id']);

        $validationErrors = $this->service->validateReadingData($location, $validated);
        if (! empty($validationErrors)) {
            return response()->json([
                'ok' => false,
                'errors' => $validationErrors,
            ], 422);
        }

        $user = $request->user();
        $reading = $this->service->createReading($location, $validated, $user);

        $outOfRange = $this->service->detectOutOfRange($reading, $location);

        return response()->json([
            'ok' => true,
            'message' => 'Data berhasil disimpan.',
            'reading' => $reading,
            'out_of_range' => $outOfRange,
        ]);
    }

    public function showCorrectionForm(Request $request, EnvironmentReading $reading)
    {
        $location = $reading->location;

        return view('monitoring.environment.correction', [
            'reading' => $reading,
            'location' => $location,
        ]);
    }

    public function storeCorrection(Request $request, EnvironmentReading $reading): JsonResponse
    {
        $validated = $request->validate([
            'temperature_c' => ['required', 'numeric', 'min:-50', 'max:100'],
            'humidity_rh' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'correction_reason' => ['required', 'string', 'max:500'],
        ]);

        $location = $reading->location;

        $validationErrors = $this->service->validateReadingData($location, $validated);
        if (! empty($validationErrors)) {
            return response()->json([
                'ok' => false,
                'errors' => $validationErrors,
            ], 422);
        }

        $user = $request->user();
        $correction = $this->service->createCorrection($reading, $validated, $validated['correction_reason'], $user);

        return response()->json([
            'ok' => true,
            'message' => 'Koreksi berhasil disimpan.',
            'correction' => $correction,
        ]);
    }

    public function apiDueList(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'ok' => true,
                'enabled' => false,
                'due' => [],
            ]);
        }

        $user = $request->user();
        $dueList = $this->service->getDueListForUser($user);

        return response()->json([
            'ok' => true,
            'enabled' => true,
            'due' => $dueList->map(function ($item) {
                return [
                    'location_id' => $item['location']->id,
                    'location_name' => $item['location']->name,
                    'location_type' => $item['location']->type->value,
                    'status' => $item['status'],
                    'active_window' => $item['active_window'],
                    'morning_filled' => $item['morning_filled'],
                    'afternoon_filled' => $item['afternoon_filled'],
                ];
            })->values(),
        ]);
    }

    public function apiLocations(Request $request): JsonResponse
    {
        $locations = $this->service->getActiveLocations();
        $today = Carbon::now();

        return response()->json([
            'ok' => true,
            'locations' => $locations->map(function (EnvironmentLocation $location) use ($today) {
                $status = $this->service->getLocationStatus($location, $today);

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'type' => $location->type->value,
                    'target_temp_min' => $location->target_temp_min,
                    'target_temp_max' => $location->target_temp_max,
                    'target_humidity_min' => $location->target_humidity_min,
                    'target_humidity_max' => $location->target_humidity_max,
                    'status' => $status['status'],
                    'morning_filled' => $status['morning_filled'],
                    'afternoon_filled' => $status['afternoon_filled'],
                ];
            }),
        ]);
    }

    public function manage(Request $request)
    {
        Gate::authorize('manage-settings');

        $locations = EnvironmentLocation::orderBy('name')->get();

        return view('monitoring.environment.manage', [
            'locations' => $locations,
        ]);
    }

    public function apiLocationsList(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $locations = EnvironmentLocation::orderBy('name')->get()->map(function (EnvironmentLocation $location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type->value,
                'target_temp_min' => $location->target_temp_min,
                'target_temp_max' => $location->target_temp_max,
                'target_humidity_min' => $location->target_humidity_min,
                'target_humidity_max' => $location->target_humidity_max,
                'is_active' => $location->is_active,
                'has_readings' => $location->readings()->exists(),
            ];
        });

        return response()->json([
            'ok' => true,
            'locations' => $locations,
        ]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:room,fridge,freezer,other'],
            'target_temp_min' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'target_temp_max' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'target_humidity_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'target_humidity_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $location = EnvironmentLocation::create($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Lokasi berhasil ditambahkan.',
            'location' => $location,
        ]);
    }

    public function updateLocation(Request $request, EnvironmentLocation $location): JsonResponse
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:room,fridge,freezer,other'],
            'target_temp_min' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'target_temp_max' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'target_humidity_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'target_humidity_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $location->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Lokasi berhasil diperbarui.',
            'location' => $location->fresh(),
        ]);
    }

    public function destroyLocation(Request $request, EnvironmentLocation $location): JsonResponse
    {
        Gate::authorize('manage-settings');

        $hasReadings = EnvironmentReading::where('location_id', $location->id)->exists();
        if ($hasReadings) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak dapat menghapus lokasi yang sudah memiliki data pembacaan.',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Lokasi berhasil dihapus.',
        ]);
    }
}
