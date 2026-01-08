<?php

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Services\InstrumentLoggingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentLoggingController extends Controller
{
    public function __construct(
        protected InstrumentLoggingService $service
    ) {}

    public function getRequirements(Request $request, Sample $sample): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'ok' => true,
                'enabled' => false,
                'requirements' => [],
            ]);
        }

        $requirements = $this->service->requirementsForSampleMethods($sample);
        $existingLogs = $this->service->getUsageLogsForSample($sample);

        $groupedRequirements = [];
        foreach ($requirements as $methodCode => $methodRequirements) {
            $groupedRequirements[$methodCode] = $methodRequirements->map(function ($req) use ($existingLogs) {
                $instrument = $req->instrument;
                $availableAssets = $instrument ? $instrument->assets->where('status', \App\Enums\InstrumentAssetStatus::ACTIVE) : collect();

                $existingLog = $existingLogs->first(function ($log) use ($req) {
                    return $log->instrumentAsset
                        && $log->instrumentAsset->instrument_id === $req->instrument_id
                        && $log->method_code === $req->method_code;
                });

                return [
                    'id' => $req->id,
                    'method_code' => $req->method_code,
                    'instrument_id' => $req->instrument_id,
                    'instrument_name' => $instrument?->name ?? 'Unknown',
                    'instrument_code' => $instrument?->code ?? '',
                    'mandatory' => $req->mandatory,
                    'usage_type' => $req->usage_type?->value ?? 'RUN',
                    'sequence' => $req->sequence,
                    'available_assets' => $availableAssets->map(fn ($asset) => [
                        'id' => $asset->id,
                        'asset_code' => $asset->asset_code,
                        'serial_number' => $asset->serial_number,
                        'location' => $asset->location,
                    ])->values(),
                    'selected_asset_id' => $existingLog?->instrument_asset_id,
                    'already_logged' => $existingLog !== null,
                ];
            })->values();
        }

        return response()->json([
            'ok' => true,
            'enabled' => true,
            'requirements' => $groupedRequirements,
            'existing_logs' => $existingLogs->map(fn ($log) => [
                'id' => $log->id,
                'instrument_asset_id' => $log->instrument_asset_id,
                'instrument_name' => $log->instrumentAsset?->instrument?->name ?? 'Unknown',
                'asset_code' => $log->instrumentAsset?->asset_code ?? '',
                'method_code' => $log->method_code,
                'logged_at' => $log->logged_at?->format('Y-m-d H:i'),
                'performed_by' => $log->performer?->name ?? 'Unknown',
            ])->values(),
        ]);
    }

    public function storeUsage(Request $request, Sample $sample): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'Pencatatan instrumen tidak diaktifkan.',
            ], 422);
        }

        $validated = $request->validate([
            'selections' => ['required', 'array'],
            'selections.*' => ['array'],
            'selections.*.*' => ['nullable', 'exists:instrument_assets,id'],
        ]);

        $user = $request->user();
        $allSelections = $validated['selections'];

        $errors = $this->service->validateSelectionsForSample($sample, $allSelections);
        if (! empty($errors)) {
            return response()->json([
                'ok' => false,
                'errors' => $errors,
                'message' => 'Validasi gagal. Beberapa instrumen wajib belum dipilih.',
            ], 422);
        }

        $logs = $this->service->createBatchUsageLogs($sample, $allSelections, $user);

        return response()->json([
            'ok' => true,
            'message' => 'Penggunaan instrumen berhasil dicatat.',
            'logs_count' => $logs->count(),
        ]);
    }

    public function checkUvvisWeighing(Request $request, Sample $sample): JsonResponse
    {
        $requiresWeighing = $this->service->requiresUvvisWeighing($sample);
        $hasWeighing = $this->service->hasCompletedUvvisWeighing($sample);

        return response()->json([
            'ok' => true,
            'requires_weighing' => $requiresWeighing,
            'has_weighing' => $hasWeighing,
            'weighing_data' => $requiresWeighing ? [
                'grams' => $sample->uvvis_weighed_grams,
                'weighed_by' => $sample->uvvisWeighedBy?->name ?? null,
                'weighed_at' => $sample->uvvis_weighed_at?->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    public function storeUvvisWeighing(Request $request, Sample $sample): JsonResponse
    {
        if (! $this->service->requiresUvvisWeighing($sample)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sampel ini tidak memerlukan penimbangan UV-VIS.',
            ], 422);
        }

        $validated = $request->validate([
            'grams' => ['required', 'numeric', 'min:0.0001', 'max:99999.9999'],
        ]);

        $user = $request->user();
        $this->service->recordUvvisWeighing($sample, (float) $validated['grams'], $user);

        return response()->json([
            'ok' => true,
            'message' => 'Data penimbangan UV-VIS berhasil disimpan.',
            'weighing_data' => [
                'grams' => $sample->fresh()->uvvis_weighed_grams,
                'weighed_by' => $user->name,
                'weighed_at' => now()->format('Y-m-d H:i'),
            ],
        ]);
    }
}
