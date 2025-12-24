<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LocalizationSettingsRequest;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class LocalizationRetentionController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly SettingsResponseBuilder $builder
    ) {
    }

    public function update(LocalizationSettingsRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validated();

        // Normalize storage_folder_path: trim slashes and set base_path consistently
        if (isset($data['retention']['storage_folder_path'])) {
            $folder = trim($data['retention']['storage_folder_path'], '/');
            $data['retention']['storage_folder_path'] = $folder;
            $data['retention']['base_path'] = $folder ? $folder . '/' : '';
        } elseif (isset($data['retention']) && array_key_exists('storage_folder_path', $data['retention'])) {
            // Explicitly set to empty/null - clear base_path too
            $data['retention']['base_path'] = '';
        }

        $payload = [];
        if (isset($data['localization'])) {
            $payload['locale'] = $data['localization'];
        }
        if (isset($data['retention'])) {
            $payload['retention'] = $data['retention'];
        }

        $this->writer->put($payload, 'UPDATE_LOCALE_RETENTION', $request->user());

        $snapshot = $this->builder->build();

        return response()->json([
            'localization' => Arr::get($snapshot, 'localization', []),
            'retention' => Arr::get($snapshot, 'retention', []),
        ]);
    }
}
