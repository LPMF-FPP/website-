<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IkuSettingsRequest;
use App\Services\IkuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class IkuSettingsController extends Controller
{
    public function __construct(
        private readonly IkuService $ikuService
    ) {}

    /**
     * GET /api/settings/iku
     * Get IKU configuration.
     */
    public function show(): JsonResponse
    {
        Gate::authorize('manage-settings');

        return response()->json([
            'iku' => $this->ikuService->getConfig(),
        ]);
    }

    /**
     * PUT /api/settings/iku
     * Update IKU configuration.
     */
    public function update(IkuSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->ikuService->updateConfig($validated, $request->user()?->id);

        return response()->json([
            'ok' => true,
            'message' => 'Pengaturan IKU berhasil disimpan.',
            'iku' => $this->ikuService->getConfig(),
        ]);
    }

    /**
     * GET /api/settings/iku/preview
     * Get IKU preview for current period.
     */
    public function preview(): JsonResponse
    {
        Gate::authorize('manage-settings');

        $result = $this->ikuService->computeForCurrentMonth();

        return response()->json([
            'ok' => true,
            'iku' => $result,
        ]);
    }
}
