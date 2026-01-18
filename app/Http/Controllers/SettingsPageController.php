<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\LocalizationSettingsRequest;
use App\Models\Document;
use App\Services\Settings\SettingsResponseBuilder;
use App\Support\DocumentTypes;
use Illuminate\Support\Facades\Gate;

class SettingsPageController extends Controller
{
    public function __construct(
        protected SettingsResponseBuilder $builder
    ) {}

    public function index()
    {
        Gate::authorize('manage-settings');

        $settings = $this->builder->build();

        $options = [
            'timezones' => LocalizationSettingsRequest::timezones(),
            'date_formats' => ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'],
            'number_formats' => ['1.234,56', '1,234.56'],
            'languages' => ['id', 'en'],
            'storage_drivers' => ['public'],
        ];

        $documentTypes = Document::query()
            ->select('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type')
            ->toArray();

        $options['document_types'] = DocumentTypes::mapOptions($documentTypes);

        $templates = $settings['templates']['list'] ?? [];

        return view('settings.index', [
            'settings' => $settings,
            'options' => $options,
            'templates' => $templates,
        ]);
    }
}
