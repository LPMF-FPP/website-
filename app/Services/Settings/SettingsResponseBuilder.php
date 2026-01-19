<?php

namespace App\Services\Settings;

use App\Models\DocumentTemplate;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\MethodInstrumentRequirement;
use App\Services\IkuService;
use App\Services\WhatsApp\NotificationService;
use App\Http\Requests\Settings\LocalizationSettingsRequest;
use App\Support\DocumentTypes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingsResponseBuilder
{
    public function __construct(
        private readonly IkuService $ikuService,
        private readonly NotificationService $notificationService
    ) {}

    public function build(): array
    {
        $flat = settings();
        $nested = settings_nest($flat);

        $retention = $this->composeRetention(Arr::get($nested, 'retention', []));

        return [
            'numbering' => Arr::get($nested, 'numbering', []),
            'templates' => [
                'active' => Arr::get($nested, 'templates.active', []),
                'list' => DocumentTemplate::orderBy('name')->get(),
            ],
            'branding' => Arr::get($nested, 'branding', []),
            'pdf' => Arr::get($nested, 'pdf', []),
            'localization' => Arr::get($nested, 'localization', Arr::get($nested, 'locale', [])),
            'retention' => $retention,
            'notifications' => $this->composeNotifications(Arr::get($nested, 'notifications', Arr::get($nested, 'automation', []))),
            'monitoring_logging' => Arr::get($nested, 'monitoring_logging', []),
            'smtp' => $this->composeSmtp(Arr::get($nested, 'smtp', [])),
            'security' => Arr::get($nested, 'security.roles', []),
            'backup' => [
                'retention_days' => (int) Arr::get($nested, 'backup.retention_days', 14),
            ],

            'iku' => $this->ikuService->getConfig(),
            'instrument_requirements' => $this->getInstrumentRequirementsData(),
        ];
    }

    public function getOptions(): array
    {
        $documentTypes = Document::query()
            ->select('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type')
            ->toArray();

        return [
            'timezones' => LocalizationSettingsRequest::timezones(),
            'date_formats' => LocalizationSettingsRequest::DATE_FORMATS,
            'number_formats' => LocalizationSettingsRequest::NUMBER_FORMATS,
            'languages' => LocalizationSettingsRequest::LANGUAGES,
            'storage_drivers' => ['public'],
            'document_types' => DocumentTypes::mapOptions($documentTypes),
        ];
    }

    public function getInstrumentRequirementsData(): array
    {
        $instruments = Instrument::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category', 'is_active']);

        $requirements = MethodInstrumentRequirement::with('instrument:id,code,name')
            ->orderBy('method_code')
            ->orderBy('sequence')
            ->get();

        $requirementsByMethod = [];
        foreach ($requirements as $req) {
            $methodCode = $req->method_code;
            if (! isset($requirementsByMethod[$methodCode])) {
                $requirementsByMethod[$methodCode] = [];
            }
            $requirementsByMethod[$methodCode][] = [
                'id' => $req->id,
                'instrument_id' => $req->instrument_id,
                'instrument_code' => $req->instrument?->code,
                'instrument_name' => $req->instrument?->name,
                'mandatory' => $req->mandatory,
                'usage_type' => $req->usage_type->value ?? $req->usage_type,
                'sequence' => $req->sequence,
            ];
        }

        return [
            'instruments_master' => $instruments,
            'requirements_by_method' => $requirementsByMethod,
            'available_methods' => MethodInstrumentRequirement::AVAILABLE_METHODS,
            'usage_types' => ['PREP', 'RUN'],
        ];
    }

    private function composeNotifications(array $notifications): array
    {
        if (isset($notifications['whatsapp']['basic_pass']) && $notifications['whatsapp']['basic_pass']) {
            $notifications['whatsapp']['basic_pass'] = '••••••••';
        }

        $defaultTemplates = $this->notificationService->getAllTemplates();
        $templates = $notifications['whatsapp']['templates'] ?? [];
        if (! is_array($templates)) {
            $templates = [];
        }
        $notifications['whatsapp']['templates'] = array_replace($defaultTemplates, $templates);

        return $notifications;
    }

    private function composeSmtp(array $smtp): array
    {
        // Return SMTP config without exposing password
        return [
            'host' => Arr::get($smtp, 'host', '127.0.0.1'),
            'port' => (int) Arr::get($smtp, 'port', 1025),
            'username' => Arr::get($smtp, 'username', ''),
            'password' => Arr::get($smtp, 'password') ? '••••••••' : '', // Mask password
            'from_address' => Arr::get($smtp, 'from_address', ''),
            'from_name' => Arr::get($smtp, 'from_name', 'LPMF LIMS'),
        ];
    }

    private function composeRetention(array $retention): array
    {
        $storagePath = Arr::get($retention, 'storage_folder_path', Arr::get($retention, 'base_path', ''));
        $retention['storage_folder_path'] = $storagePath;

        $disk = Arr::get($retention, 'storage_driver', 'public');
        $relative = trim($storagePath ?: Arr::get($retention, 'base_path', ''), '/');

        try {
            $retention['resolved_storage_path'] = rtrim(Storage::disk($disk)->path($relative), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        } catch (\Throwable $e) {
            $retention['resolved_storage_path'] = null;
        }

        return $retention;
    }
}
