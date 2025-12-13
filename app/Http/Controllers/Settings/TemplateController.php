<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-settings');

        $templates = DocumentTemplate::orderBy('name')->get();

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:docx,html,htm', 'max:5120'],
        ]);

        $disk = settings('retention.storage_driver', 'local');
        $base = trim(settings('retention.base_path', 'official_docs/'), '/');
        $storedPath = $request->file('file')->store($base.'/templates', $disk);

        $template = DocumentTemplate::updateOrCreate(
            ['code' => Str::upper($validated['code'])],
            [
                'name' => $validated['name'],
                'storage_path' => $storedPath,
                'meta' => ['uploaded_at' => now()->toISOString()],
                'updated_by' => $request->user()->id,
            ]
        );

        Audit::log('UPLOAD_TEMPLATE', $template->code, null, ['path' => $storedPath]);

        return response()->json($template, 201);
    }

    public function activate(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:100'],
        ]);

        $active = settings('templates.active', []);
        $before = $active;
        $active[$validated['type']] = $validated['code'];

        SystemSetting::updateOrCreate(
            ['key' => 'templates.active'],
            ['value' => $active, 'updated_by' => $request->user()->id]
        );

        settings_flush_cache();

        Audit::log('ACTIVATE_TEMPLATE', $validated['type'], $before, $active);

        return response()->json($active);
    }
}
