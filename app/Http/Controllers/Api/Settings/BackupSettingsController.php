<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BackupSettingsRequest;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class BackupSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly SettingsResponseBuilder $builder
    ) {}

    public function update(BackupSettingsRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $retentionDays = (int) Arr::get($request->validated(), 'backup.retention_days', 14);

        $this->writer->put([
            'backup' => [
                'retention_days' => $retentionDays,
            ],
        ], 'UPDATE_BACKUP_SETTINGS', $request->user());

        $snapshot = $this->builder->build();

        return response()->json([
            'backup' => Arr::get($snapshot, 'backup', []),
        ]);
    }
}
