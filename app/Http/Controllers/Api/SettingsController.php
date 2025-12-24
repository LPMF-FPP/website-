<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LocalizationSettingsRequest;
use App\Services\Settings\SettingsResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsResponseBuilder $builder)
    {
    }

    public function index(): JsonResponse
    {
        Gate::authorize('manage-settings');

        $payload = $this->builder->build();

        return response()->json([
            'settings' => $payload,
            'options' => [
                'timezones' => LocalizationSettingsRequest::TIMEZONES,
                'date_formats' => LocalizationSettingsRequest::DATE_FORMATS,
                'number_formats' => LocalizationSettingsRequest::NUMBER_FORMATS,
                'languages' => LocalizationSettingsRequest::LANGUAGES,
            ],
        ]);
    }
}
