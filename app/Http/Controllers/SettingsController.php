<?php

namespace App\Http\Controllers;

use App\Enums\InstrumentUsageType;
use App\Http\Requests\Settings\LocalizationSettingsRequest;
use App\Models\MethodInstrumentRequirement;
use App\Models\SystemSetting;
use App\Services\NumberingService;
use App\Services\Settings\SettingsResponseBuilder;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

use function settings;
use function settings_flatten;
use function settings_forget_cache;

class SettingsController extends Controller
{
    private const ALLOWED_DATE_FORMATS = ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'];

    private const ALLOWED_NUMBER_FORMATS = ['1.234,56', '1,234.56'];

    private const ALLOWED_LANGUAGES = ['id', 'en'];

    private const ALLOWED_ROOTS = [
        'numbering',
        'branding',
        'pdf',
        'locale',
        'retention',
        'notifications',
        'automation',
        'templates',
        'security',
        'monitoring_logging',
    ];

    public function update(Request $request)
    {
        try {
            Gate::authorize('manage-settings');
            $incoming = $request->only(self::ALLOWED_ROOTS);

            if (isset($incoming['templates']['list'])) {
                unset($incoming['templates']['list']);
            }

            // Normalize security roles structure
            if (isset($incoming['security']) && ! isset($incoming['security']['roles'])) {
                $incoming['security']['roles'] = [
                    'can_manage_settings' => Arr::get($incoming['security'], 'can_manage_settings', []),
                    'can_manage_users' => Arr::get($incoming['security'], 'can_manage_users', []),
                    'can_issue_number' => Arr::get($incoming['security'], 'can_issue_number', []),
                ];
                unset(
                    $incoming['security']['can_manage_settings'],
                    $incoming['security']['can_manage_users'],
                    $incoming['security']['can_issue_number']
                );
            }
        } catch (\Exception $e) {
            Log::error('Settings update error (pre-processing):', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }

        // Light validation for locale keys
        if (isset($incoming['locale']) && is_array($incoming['locale'])) {
            $l = &$incoming['locale'];
            $allowedTimezones = LocalizationSettingsRequest::timezones();
            if (isset($l['timezone']) && ! in_array($l['timezone'], $allowedTimezones, true)) {
                $l['timezone'] = $allowedTimezones[0] ?? 'UTC';
            }
            if (isset($l['date_format']) && ! in_array($l['date_format'], self::ALLOWED_DATE_FORMATS, true)) {
                $l['date_format'] = self::ALLOWED_DATE_FORMATS[0];
            }
            if (isset($l['number_format']) && ! in_array($l['number_format'], self::ALLOWED_NUMBER_FORMATS, true)) {
                $l['number_format'] = self::ALLOWED_NUMBER_FORMATS[0];
            }
            if (isset($l['language']) && ! in_array($l['language'], self::ALLOWED_LANGUAGES, true)) {
                $l['language'] = self::ALLOWED_LANGUAGES[0];
            }
        }

        try {
            if (isset($incoming['automation']) && ! isset($incoming['notifications'])) {
                $incoming['notifications'] = $incoming['automation'];
            }

            $flat = settings_flatten($incoming);
            // Log flattened keys
            try {
                Log::info('Settings update: flattened keys', [
                    'keys' => array_keys($flat),
                ]);
            } catch (\Throwable $e) {
                // non-fatal logging issue
            }

            // Final guard: do not accept empty flattened payload
            if (empty($flat)) {
                Log::warning('Settings update: empty settings payload received', [
                    'content_type' => $request->header('Content-Type'),
                    'method' => $request->method(),
                    'raw_body' => $request->getContent(),
                ]);

                return response()->json([
                    'error' => 'Empty settings payload',
                ], 422);
            }
            $before = [];
            $after = [];
            $skipped = [];

            foreach ($flat as $key => $value) {
                $root = explode('.', $key, 2)[0];
                if (! in_array($root, self::ALLOWED_ROOTS, true)) {
                    // Log ignored root
                    Log::info('Settings update: skipping key with disallowed root', ['key' => $key, 'root' => $root]);

                    continue;
                }

                // Null handling: if value is null due to ConvertEmptyStringsToNull and key was submitted,
                // we consider it intentional. Persist null ONLY if the column allows null; otherwise skip.
                // Our 'system_settings.value' column is JSON and not nullable in migration, so we skip persisting
                // nulls to avoid DB constraint violation. If you later make it nullable, flip this flag.
                $valueColumnNullable = false; // based on migration: $table->json('value');
                if ($value === null) {
                    if ($valueColumnNullable) {
                        // Allow storing null
                        Log::info('Settings update: setting null value', ['key' => $key]);
                    } else {
                        Log::info('Settings update: skipping null (non-nullable column)', ['key' => $key]);
                        $skipped[$key] = 'null_not_allowed';

                        continue;
                    }
                }

                $current = SystemSetting::where('key', $key)->first();
                $before[$key] = $current?->value;

                Log::info('Settings update: updating key', [
                    'key' => $key,
                    'previous' => $current?->value,
                    'next' => $value,
                ]);
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'updated_by' => $request->user()->id,
                    ]
                );
                $after[$key] = $value;
            }

            // Clear caches
            settings_forget_cache();
            try {
                cache()->forget('app.settings');
                cache()->forget('settings.all');
                cache()->forget('settings.flat');
            } catch (\Throwable $e) {
                // ignore cache clearing issues
            }
            Audit::log('UPDATE_SETTINGS', null, $before, $after);
            $request->attributes->set('audit_before', $before);
            $request->attributes->set('audit_after', $after);
            $request->attributes->set('audit_meta', [
                'updated_keys' => array_keys($after),
                'skipped' => $skipped,
            ]);

            return response()->json(['ok' => true, 'message' => 'Settings saved successfully']);
        } catch (\Exception $e) {
            Log::error('Settings update error (processing):', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'incoming' => $incoming ?? null,
            ]);

            return response()->json(['error' => 'Failed to save settings: '.$e->getMessage()], 500);
        }
    }

    public function preview(Request $request, NumberingService $service)
    {
        Gate::authorize('manage-settings');

        $data = $request->validate([
            'scope' => ['required', 'string'],
            'config' => ['nullable', 'array'],
        ]);

        $scope = $data['scope'];
        $config = $data['config'] ?? [];
        $pattern = data_get($config, "numbering.$scope.pattern")
            ?? data_get($config, "$scope.pattern")
            ?? settings("numbering.$scope.pattern");

        $example = $service->example($scope, $pattern);

        return response()->json(['example' => $example]);
    }

    public function uploadBrandAsset(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'type' => ['required', 'in:logo,digital_stamp,watermark'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $disk = settings('retention.storage_driver', 'public');
        $path = $request->file('file')->store('settings', $disk);

        // Determine dot-notated key based on type
        $map = [
            'logo' => 'branding.logo_path',
            'digital_stamp' => 'branding.digital_stamp_path',
            'watermark' => 'branding.watermark_path',
        ];
        $key = $map[$validated['type']];

        // Save as individual setting key instead of whole branding array
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $path, 'updated_by' => $request->user()->id]
        );

        settings_forget_cache();

        Audit::log('UPLOAD_BRAND_ASSET', $validated['type'], null, ['key' => $key, 'path' => $path]);

        return response()->json(['path' => $path]);
    }


    public function saveInstrumentRequirements(Request $request, SettingsResponseBuilder $settingsBuilder)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'requirements_by_method' => ['required', 'array'],
            'requirements_by_method.*' => ['array'],
            'requirements_by_method.*.*.instrument_id' => ['required', 'integer', 'exists:instruments,id'],
            'requirements_by_method.*.*.mandatory' => ['required', 'boolean'],
            'requirements_by_method.*.*.usage_type' => ['required', 'string', Rule::enum(InstrumentUsageType::class)],
            'requirements_by_method.*.*.sequence' => ['required', 'integer', 'min:1'],
        ]);

        $requirementsByMethod = $validated['requirements_by_method'];

        try {
            DB::transaction(function () use ($requirementsByMethod) {
                foreach (MethodInstrumentRequirement::AVAILABLE_METHODS as $methodCode) {
                    MethodInstrumentRequirement::where('method_code', $methodCode)->delete();

                    $requirements = $requirementsByMethod[$methodCode] ?? [];
                    foreach ($requirements as $req) {
                        MethodInstrumentRequirement::create([
                            'method_code' => $methodCode,
                            'instrument_id' => $req['instrument_id'],
                            'mandatory' => $req['mandatory'],
                            'usage_type' => $req['usage_type'],
                            'sequence' => $req['sequence'],
                        ]);
                    }
                }
            });

            Audit::log('UPDATE_INSTRUMENT_REQUIREMENTS', null, null, [
                'methods_updated' => array_keys($requirementsByMethod),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Instrument requirements saved successfully',
                'instrument_requirements' => $settingsBuilder->getInstrumentRequirementsData(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save instrument requirements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to save instrument requirements: '.$e->getMessage()], 500);
        }
    }
}
