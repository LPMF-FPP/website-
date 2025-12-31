<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DocumentTemplateActivateRequest;
use App\Http\Requests\Settings\DocumentTemplateUploadRequest;
use App\Models\DocumentTemplate;
use App\Services\Settings\SettingsWriter;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function __construct(private readonly SettingsWriter $writer)
    {
    }

    public function index(): JsonResponse
    {
        Gate::authorize('manage-settings');

        return response()->json(
            DocumentTemplate::orderBy('name')->get()
        );
    }

    public function upload(DocumentTemplateUploadRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $validated = $request->validated();

        $disk = 'local';
        $path = $request->file('file')->store('templates', $disk);

        $existing = DocumentTemplate::where('code', Str::upper($validated['code']))->first();

        $template = DocumentTemplate::updateOrCreate(
            ['code' => Str::upper($validated['code'])],
            [
                'name' => $validated['name'],
                'storage_path' => $path,
                'meta' => array_merge($existing?->meta ?? [], [
                    'disk' => $disk,
                    'uploaded_at' => now()->toIso8601String(),
                ]),
                'updated_by' => $request->user()->id,
            ]
        );

        Audit::log('UPLOAD_TEMPLATE', $template->code, null, ['path' => $path]);

        return response()->json($template, 201);
    }

    public function activate(DocumentTemplateActivateRequest $request, DocumentTemplate $template): JsonResponse
    {
        Gate::authorize('manage-settings');
        $validated = $request->validated();

        $active = settings('templates.active', []);
        $active[$validated['type']] = $template->code;

        $this->writer->put(['templates.active' => $active], 'ACTIVATE_TEMPLATE', $request->user());

        return response()->json([
            'active' => $active,
        ]);
    }

    public function destroy(DocumentTemplate $template): JsonResponse
    {
        Gate::authorize('manage-settings');

        $disk = data_get($template->meta, 'disk', 'local');
        if ($template->storage_path && Storage::disk($disk)->exists($template->storage_path)) {
            Storage::disk($disk)->delete($template->storage_path);
        }
        $this->deleteTemplateArtifacts($disk, $template->storage_path);

        Audit::log('DELETE_TEMPLATE', $template->code, ['path' => $template->storage_path], null);

        $template->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(DocumentTemplate $template)
    {
        Gate::authorize('manage-settings');

        $disk = data_get($template->meta, 'disk', 'local');
        $path = $template->storage_path;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404, 'Template file not found');
        }

        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            abort(500, 'Failed to read template file.');
        }
        $mime = Storage::disk($disk)->mimeType($path);
        if (!$mime || $mime === 'application/octet-stream') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/octet-stream';
        }
        $filename = $template->name . '.' . pathinfo($path, PATHINFO_EXTENSION);

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function deleteTemplateArtifacts(string $disk, ?string $path): void
    {
        if (!$path) {
            return;
        }

        $normalized = trim($path, '/');
        if (!str_starts_with($normalized, 'templates/')) {
            return;
        }

        $parts = explode('/', $normalized);
        if (count($parts) < 4) {
            return;
        }

        $filename = array_pop($parts);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $format = array_pop($parts);
        $group = implode('/', $parts);
        $formats = ['pdf', 'docx', 'html'];

        foreach ($formats as $candidateFormat) {
            $candidate = $group.'/'.$candidateFormat.'/'.$base.'.'.$candidateFormat;
            if (Storage::disk($disk)->exists($candidate)) {
                Storage::disk($disk)->delete($candidate);
            }
        }
    }
}
