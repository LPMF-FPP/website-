<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NumberingPreviewRequest;
use App\Http\Requests\Settings\NumberingSettingsRequest;
use App\Services\NumberingService;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class NumberingController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly NumberingService $numbering
    ) {
    }

    public function current(): JsonResponse
    {
        Gate::authorize('manage-settings');

        $scopes = ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'];
        $result = [];

        foreach ($scopes as $scope) {
            try {
                $snapshot = $this->numbering->currentSnapshot($scope, []);
                $result[$scope] = [
                    'current' => $snapshot['current'] ?? null,
                    'next' => $snapshot['next'] ?? '',
                    'pattern' => $snapshot['pattern'] ?? '',
                ];
            } catch (\Exception $e) {
                \Log::warning("Failed to get current numbering for {$scope}: " . $e->getMessage());
                $result[$scope] = [
                    'current' => null,
                    'next' => '',
                    'pattern' => '',
                ];
            }
        }

        return response()->json($result);
    }

    public function update(NumberingSettingsRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validated();

        $this->writer->put(['numbering' => $data['numbering']], 'UPDATE_NUMBERING', $request->user());

        return response()->json([
            'numbering' => Arr::get($this->writer->snapshot(), 'numbering', []),
        ]);
    }

    public function updateScope(string $scope, \Illuminate\Http\Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        // Validate the scope parameter
        $validScopes = ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'];
        if (!in_array($scope, $validScopes)) {
            return response()->json([
                'message' => 'Invalid scope. Valid scopes are: ' . implode(', ', $validScopes),
                'errors' => ['scope' => ['Invalid scope']],
            ], 422);
        }

        // Validate the input data
        $validated = $request->validate([
            'pattern' => ['required', 'string', 'max:255'],
            'reset' => ['required', 'string', 'in:daily,weekly,monthly,yearly,never'],
            'start_from' => ['required', 'integer', 'min:1'],
            'per_test_type' => ['sometimes', 'nullable', 'boolean'],
        ], [
            'pattern.required' => 'Pattern wajib diisi.',
            'pattern.string' => 'Pattern harus berupa teks.',
            'pattern.max' => 'Pattern maksimal 255 karakter.',
            'reset.required' => 'Reset period wajib dipilih.',
            'reset.in' => 'Reset period harus salah satu dari: daily, weekly, monthly, yearly, never.',
            'start_from.required' => 'Start from wajib diisi.',
            'start_from.integer' => 'Start from harus berupa angka.',
            'start_from.min' => 'Start from minimal 1.',
        ]);

        // Save each field as a separate dot-notated key for consistency
        // This ensures settings('numbering.lhu.pattern') works correctly
        $this->writer->put(
            [
                "numbering.{$scope}.pattern" => $validated['pattern'],
                "numbering.{$scope}.reset" => $validated['reset'],
                "numbering.{$scope}.start_from" => $validated['start_from'],
            ],
            'UPDATE_NUMBERING_SCOPE_' . strtoupper($scope),
            $request->user()
        );

        // Also delete any legacy key that might conflict
        \App\Models\SystemSetting::where('key', "numbering.{$scope}")->delete();
        settings_forget_cache();

        return response()->json([
            'scope' => $scope,
            'config' => $validated,
            'message' => 'Pengaturan penomoran berhasil disimpan.',
        ]);
    }

    public function preview(NumberingPreviewRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validated();
        $scope = $data['scope'];
        $config = $data['config'] ?? [];
        
        // Extract pattern from various possible structures
        $pattern = data_get($config, "numbering.$scope.pattern")
            ?? data_get($config, "$scope.pattern")
            ?? data_get($config, "pattern")
            ?? settings("numbering.$scope.pattern");

        // Validate pattern
        if (empty($pattern)) {
            return response()->json([
                'message' => 'Pattern penomoran tidak ditemukan. Silakan isi pattern terlebih dahulu.',
                'errors' => ['pattern' => ['Pattern wajib diisi']],
            ], 422);
        }

        // Log for debugging
        \Log::info('Numbering preview request', [
            'scope' => $scope,
            'pattern' => $pattern,
            'config_structure' => array_keys($config),
        ]);

        try {
            $example = $this->numbering->example($scope, $pattern);
            
            return response()->json([
                'example' => $example,
                'preview' => $example,  // For frontend compatibility
                'extractedValue' => $example,  // For frontend compatibility
                'scope' => $scope,
                'pattern' => $pattern,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate numbering preview', [
                'scope' => $scope,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Gagal membuat preview: ' . $e->getMessage(),
                'errors' => ['preview' => [$e->getMessage()]],
            ], 422);
        }
    }
}
