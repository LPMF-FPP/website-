<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\SystemSetting;
use App\Services\NumberingService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use function settings;
use function settings_flatten;
use function settings_forget_cache;
use function settings_nest;

class SettingsController extends Controller
{
    private const ALLOWED_TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'];
    private const ALLOWED_DATE_FORMATS = ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'];
    private const ALLOWED_NUMBER_FORMATS = ['1.234,56', '1,234.56'];
    private const ALLOWED_LANGUAGES = ['id', 'en'];
    public function index()
    {
        Gate::authorize('manage-settings');
        // Provide nested settings to the view for initial prefill
        $flat = settings();
        $settings = settings_nest($flat);

        return view('settings.index', [
            'settings' => $settings,
        ]);
    }

    public function show()
    {
        Gate::authorize('manage-settings');

    $flat = settings();
    $settings = settings_nest($flat);

        $templates = DocumentTemplate::orderBy('name')->get();

        return response()->json([
            'settings' => $settings,
            'numbering' => Arr::get($settings, 'numbering', []),
            'branding' => Arr::get($settings, 'branding', []),
            'pdf' => Arr::get($settings, 'pdf', []),
            'locale' => Arr::get($settings, 'locale', []),
            'retention' => Arr::get($settings, 'retention', []),
            'automation' => Arr::get($settings, 'notifications', Arr::get($settings, 'automation', [])),
            'notifications' => Arr::get($settings, 'notifications', Arr::get($settings, 'automation', [])),
            'templates' => [
                'active' => Arr::get($settings, 'templates.active', []),
                'list' => $templates,
            ],
            'security' => Arr::get($settings, 'security.roles', []),
            'options' => [
                'timezones' => self::ALLOWED_TIMEZONES,
                'date_formats' => self::ALLOWED_DATE_FORMATS,
                'number_formats' => self::ALLOWED_NUMBER_FORMATS,
                'languages' => self::ALLOWED_LANGUAGES,
                'storage_drivers' => ['local', 's3'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        try {
            Gate::authorize('manage-settings');
            $incoming = $this->extractPayload($request);

            if (isset($incoming['templates']['list'])) {
                unset($incoming['templates']['list']);
            }

            // Normalize security roles structure
            if (isset($incoming['security']) && !isset($incoming['security']['roles'])) {
                $incoming['security']['roles'] = [
                    'can_manage_settings' => Arr::get($incoming['security'], 'can_manage_settings', []),
                    'can_issue_number' => Arr::get($incoming['security'], 'can_issue_number', []),
                ];
                unset($incoming['security']['can_manage_settings'], $incoming['security']['can_issue_number']);
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
            $l =& $incoming['locale'];
            if (isset($l['timezone']) && !in_array($l['timezone'], self::ALLOWED_TIMEZONES, true)) {
                $l['timezone'] = self::ALLOWED_TIMEZONES[0];
            }
            if (isset($l['date_format']) && !in_array($l['date_format'], self::ALLOWED_DATE_FORMATS, true)) {
                $l['date_format'] = self::ALLOWED_DATE_FORMATS[0];
            }
            if (isset($l['number_format']) && !in_array($l['number_format'], self::ALLOWED_NUMBER_FORMATS, true)) {
                $l['number_format'] = self::ALLOWED_NUMBER_FORMATS[0];
            }
            if (isset($l['language']) && !in_array($l['language'], self::ALLOWED_LANGUAGES, true)) {
                $l['language'] = self::ALLOWED_LANGUAGES[0];
            }
        }

        try {
            if (isset($incoming['automation']) && !isset($incoming['notifications'])) {
                $incoming['notifications'] = $incoming['automation'];
            }

            $allowedRoots = [
                'numbering', 'branding', 'pdf', 'locale', 'retention', 'notifications', 'automation', 'templates', 'security',
            ];

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
                if (!in_array($root, $allowedRoots, true)) {
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

    public function test()
    {
        Gate::authorize('manage-settings');

        return response()->json(['ok' => true]);
    }

    public function uploadBrandAsset(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'type' => ['required', 'in:logo,digital_stamp,watermark'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $disk = settings('retention.storage_driver', 'local');
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

    protected function extractPayload(Request $request): array
    {
        // 1) Try JSON body
        $incoming = $request->json()->all();
        if ($incoming === []) {
            // Treat explicit empty array as empty to allow fallback
            $incoming = null;
        }

        // 2) Try a JSON string in 'payload'
        if (!$incoming) {
            $payload = $request->input('payload');
            if (is_string($payload) && $payload !== '') {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $incoming = $decoded;
                }
            }
        }

        // 3) Try explicit 'settings' field (array or JSON string)
        if (!$incoming) {
            $settingsInput = $request->input('settings');
            if (is_array($settingsInput)) {
                $incoming = $settingsInput;
            } elseif (is_string($settingsInput) && $settingsInput !== '') {
                $decoded = json_decode($settingsInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $incoming = $decoded;
                }
            }
        }

        // 4) Final fallback for form-urlencoded or multipart form-data
        if (!$incoming) {
            $incoming = $request->all();
            // If JSON content-type forces input() to JSON and returns empty,
            // but form parameters exist in $request->request, use those.
            if ($incoming === [] && $request->request->count() > 0) {
                $incoming = $request->request->all();
            }
        }

        // Unwrap if nested under 'settings'
        if (isset($incoming['settings']) && is_array($incoming['settings'])) {
            $incoming = $incoming['settings'];
        }

        // If still explicitly empty array after unwrapping, perform one more fallback
        if ($incoming === []) {
            $incoming = $request->all();
            if ($incoming === [] && $request->request->count() > 0) {
                $incoming = $request->request->all();
            }
            if (isset($incoming['settings']) && is_array($incoming['settings'])) {
                $incoming = $incoming['settings'];
            }
        }

        if (!is_array($incoming)) {
            throw ValidationException::withMessages([
                'settings' => 'Invalid settings payload.',
            ]);
        }

        // Only allow specific root keys
        $allowedRoots = [
            'numbering', 'branding', 'pdf', 'locale', 'retention', 'notifications', 'automation', 'templates', 'security',
        ];

        $filtered = [];
        foreach ($allowedRoots as $root) {
            if (array_key_exists($root, $incoming)) {
                $filtered[$root] = $incoming[$root];
            }
        }

        return $filtered;
    }
}
