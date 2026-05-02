<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GoogleDriveSettingsRequest;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GoogleDriveSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly SettingsResponseBuilder $builder,
    ) {}

    public function update(GoogleDriveSettingsRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validated();
        $googleDrive = $data['google_drive'];

        $googleDrive['folder_id'] = $googleDrive['folder_id'] ?: null;
        $googleDrive['uploader_user_id'] = $googleDrive['uploader_user_id'] ?? null;

        $this->writer->put([
            'google_drive' => $googleDrive,
        ], 'UPDATE_GOOGLE_DRIVE_SETTINGS', $request->user());

        $snapshot = $this->builder->build();

        return response()->json([
            'google_drive' => Arr::get($snapshot, 'google_drive', []),
        ]);
    }
}
